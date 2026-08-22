<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Communication\Jobs\ProcessIncomingMessage;
use Modules\Communication\Support\WhatsAppInboundPayload;
use Modules\Customers\Services\CustomerService;

class SimulateWhatsAppMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulate:whatsapp 
                            {message : Text body (or caption for media)}
                            {--phone=15551234567 : The phone number of the sender}
                            {--name=QA Tester : The profile name of the sender}
                            {--type=text : WhatsApp payload type: text|image|audio|video|document}
                            {--file= : Local file path to attach (for non-text types)}
                            {--mime= : Optional MIME type override (for media)}
                            {--caption= : Caption for media (defaults to the message argument)}
                            {--all-types : Send one inbound message per known MIME category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an incoming WhatsApp message payload for E2E testing without an active webhook.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $messageText = $this->argument('message');
        $phone = $this->option('phone');
        $name = $this->option('name');
        $type = (string) $this->option('type');
        $filePath = $this->option('file');
        $mimeOverride = $this->option('mime');
        $caption = $this->option('caption') ?: $messageText;
        $sendAllTypes = (bool) $this->option('all-types');

        // Ensure we hit the "known customer" WhatsApp pipeline (attachment download/processing).
        // Otherwise ProcessIncomingMessage will route to "unknown number" and skip attachments.
        app(CustomerService::class)->findOrCreateByPhone($phone, [
            'name' => $name,
            'whatsapp_id' => $phone,
            'daily_ai_cost_limit' => 100,
        ]);

        if ($sendAllTypes) {
            $this->info("Simulating all inbound media types for {$name} ({$phone})...");

            $samples = $this->allMimeSamples();

            foreach ($samples as $idx => $sample) {
                $storedPath = 'simulated-cli/'.$idx.'-'.$sample['filename'];
                Storage::disk('local')->put($storedPath, $sample['bytes']);

                $payload = WhatsAppInboundPayload::media(
                    fromPhone: $phone,
                    profileName: $name,
                    mimeType: $sample['mime_type'],
                    caption: $caption,
                    filename: $sample['filename'],
                    mediaId: 'cli_media_'.$idx,
                    simulatedPath: $storedPath,
                );

                $this->dispatchPayload($payload, $idx);
            }

            return;
        }

        if ($type === 'text') {
            $payload = WhatsAppInboundPayload::text($phone, $name, $messageText);
            $this->dispatchPayload($payload);

            return;
        }

        if (! $filePath) {
            $this->error('Missing --file. Provide a local file path for media simulation.');

            return;
        }

        $absPath = realpath($filePath);
        if (! $absPath || ! is_file($absPath)) {
            $this->error('Invalid --file path: '.(string) $filePath);

            return;
        }

        $mimeType = $mimeOverride ?: (mime_content_type($absPath) ?: 'application/octet-stream');
        $originalFilename = basename($absPath);
        $bytes = file_get_contents($absPath);
        if ($bytes === false) {
            $this->error('Failed to read --file: '.(string) $filePath);

            return;
        }

        $storedPath = 'simulated-cli/'.Str::uuid().'-'.$originalFilename;
        Storage::disk('local')->put($storedPath, $bytes);

        $payload = WhatsAppInboundPayload::media(
            fromPhone: $phone,
            profileName: $name,
            mimeType: $mimeType,
            caption: $caption,
            filename: $originalFilename,
            mediaId: 'cli_media_'.Str::uuid(),
            simulatedPath: $storedPath,
        );

        $this->dispatchPayload($payload);
    }

    protected function dispatchPayload(array $payload, ?int $idx = null): void
    {
        $label = $idx !== null ? "sample #{$idx}" : 'message';
        $this->info("Dispatching inbound {$label} via ProcessIncomingMessage...");

        ProcessIncomingMessage::dispatch($payload, 'whatsapp');

        $this->info("Dispatched inbound {$label}. Check queue worker output.");
    }

    /**
     * @return array<int, array{mime_type:string,filename:string,bytes:string}>
     */
    protected function allMimeSamples(): array
    {
        // Tiny/valid PNG (1x1 transparent) + WAV (silence) so Gemini media analysis can succeed.
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMBAp6XK3kAAAAASUVORK5CYII=');
        $wavBytes = $this->makeSilentWavBytes(8000, 1);

        // For video/docs/spreadsheet/archive we don't run AI extractors in ProcessAttachment,
        // so bytes can be placeholders.
        $pdfBytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";

        return [
            ['mime_type' => 'image/png', 'filename' => 'sample.png', 'bytes' => (string) $pngBytes],
            ['mime_type' => 'audio/wav', 'filename' => 'note.wav', 'bytes' => $wavBytes],

            ['mime_type' => 'video/mp4', 'filename' => 'video.mp4', 'bytes' => 'dummy-video-bytes'],
            ['mime_type' => 'application/pdf', 'filename' => 'doc.pdf', 'bytes' => $pdfBytes],
            ['mime_type' => 'application/msword', 'filename' => 'doc.doc', 'bytes' => 'dummy-doc-bytes'],
            ['mime_type' => 'text/plain', 'filename' => 'note.txt', 'bytes' => 'dummy-text-bytes'],
            ['mime_type' => 'application/vnd.ms-excel', 'filename' => 'sheet.xls', 'bytes' => 'dummy-xls-bytes'],
            ['mime_type' => 'application/zip', 'filename' => 'archive.zip', 'bytes' => 'dummy-zip-bytes'],
            ['mime_type' => 'application/octet-stream', 'filename' => 'blob.bin', 'bytes' => 'dummy-blob-bytes'],
        ];
    }

    protected function makeSilentWavBytes(int $sampleRate, int $durationSeconds): string
    {
        $numChannels = 1;
        $bitsPerSample = 16;
        $numSamples = $sampleRate * $durationSeconds;
        $dataChunkSize = $numSamples * $numChannels * ($bitsPerSample / 8);

        $riffChunkSize = 36 + $dataChunkSize;
        $byteRate = $sampleRate * $numChannels * ($bitsPerSample / 8);
        $blockAlign = $numChannels * ($bitsPerSample / 8);

        $header =
            'RIFF'.pack('V', $riffChunkSize).
            'WAVE'.
            'fmt '.pack('V', 16).
            pack('v', 1). // PCM
            pack('v', $numChannels).
            pack('V', $sampleRate).
            pack('V', (int) $byteRate).
            pack('v', (int) $blockAlign).
            pack('v', $bitsPerSample).
            'data'.pack('V', $dataChunkSize);

        // 16-bit little-endian silence: 0x0000 repeated.
        $silence = str_repeat("\0\0", $numSamples);

        return $header.$silence;
    }
}
