<?php

namespace Modules\Attachments\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Models\Message;

class ProcessAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 120;

    private const MAX_ATTACHMENTS_PER_MESSAGE = 10;

    private const MAX_TRANSCRIBE_BYTES = 20971520; // 20 MB Gemini inline limit

    private const ALLOWED_AUDIO_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/mpga',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'audio/oga',
        'audio/opus',
        'audio/webm',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/aac',
        'audio/x-aac',
        'audio/flac',
    ];

    public function __construct(
        public Attachment $attachment,
        public Message $message
    ) {}

    public function handle(): void
    {
        Log::info("Processing attachment {$this->attachment->uuid}");
        $this->attachment->update(['processing_status' => 'processing']);

        try {
            $mime = $this->normalizedMime($this->attachment->mime_type);
            $path = $this->attachment->stored_path;
            $analyzeAttachments = (bool) data_get($this->message->metadata, 'analyze_attachments', false);

            if (str_starts_with($mime, 'audio/')) {
                if (! in_array($mime, self::ALLOWED_AUDIO_MIME_TYPES, true)) {
                    Log::info("Skipping transcription for unsupported audio MIME {$mime}", ['attachment_id' => $this->attachment->id]);
                    $this->finalize();

                    return;
                }
                if (($this->attachment->size_bytes ?? 0) > self::MAX_TRANSCRIBE_BYTES) {
                    Log::warning('Skipping transcription for oversized audio attachment', [
                        'attachment_id' => $this->attachment->id,
                        'size_bytes' => $this->attachment->size_bytes,
                    ]);
                    $this->finalize();

                    return;
                }
                $this->processAudio($path, $mime);
            } elseif ($analyzeAttachments && str_starts_with($mime, 'image/')) {
                $this->processImage($path, $mime);
            }

            $this->finalize();
        } catch (\Exception $e) {
            Log::error("Failed to process attachment {$this->attachment->uuid}: ".$e->getMessage());
            $this->attachment->update(['processing_status' => 'failed']);
            throw $e;
        }
    }

    protected function processAudio(string $path, string $mime): void
    {
        $geminiKey = config('services.gemini.key');
        if (! empty($geminiKey)) {
            $this->transcribeWithGemini(
                $path,
                $this->geminiAudioMime($mime),
                'Transcribe this voice note verbatim in the original language. Return only the spoken words, with no commentary, labels, or markdown.'
            );

            return;
        }

        $openaiKey = config('services.openai.key');
        if (! empty($openaiKey)) {
            $this->transcribeWithWhisper($path, $openaiKey);

            return;
        }

        Log::warning('No Gemini or OpenAI API key configured. Skipping audio transcription.', [
            'attachment_id' => $this->attachment->id,
        ]);
    }

    protected function processImage(string $path, string $mime): void
    {
        $geminiKey = config('services.gemini.key');
        if (! empty($geminiKey)) {
            $this->transcribeWithGemini(
                $path,
                $mime,
                'Extract all text from this image and provide a brief description. Return only the extracted text and description.'
            );

            return;
        }

        $openaiKey = config('services.openai.key');
        if (! empty($openaiKey)) {
            $this->describeImageWithOpenAI($path, $openaiKey, $mime);

            return;
        }

        Log::warning('No Gemini or OpenAI API key configured. Skipping image analysis.', [
            'attachment_id' => $this->attachment->id,
        ]);
    }

    protected function transcribeWithGemini(string $path, string $mime, string $prompt): void
    {
        $apiKey = config('services.gemini.key');
        $modelName = config('services.gemini.media_model')
            ?: config('services.gemini.model', 'gemini-3.5-flash-lite');
        $fileContents = Storage::disk($this->attachment->disk)->get($path);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $mime,
                                'data' => base64_encode($fileContents),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
        $response = Http::timeout(90)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception('Gemini media API failed: '.$response->body());
        }

        $text = $this->extractGeminiText($response);
        if ($text === '') {
            throw new \Exception('Gemini media API returned an empty transcript.');
        }

        $this->attachment->update([
            'ai_transcript' => $text,
        ]);
    }

    protected function transcribeWithWhisper(string $path, string $token): void
    {
        $fileStream = Storage::disk($this->attachment->disk)->readStream($path);

        $response = Http::withToken($token)
            ->timeout(60)
            ->attach('file', $fileStream, $this->attachment->original_name)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
            ]);

        if ($response->successful()) {
            $text = $response->json('text');
            $this->attachment->update([
                'ai_transcript' => $text,
            ]);
        } else {
            throw new \Exception('Whisper API failed: '.$response->body());
        }
    }

    protected function describeImageWithOpenAI(string $path, string $token, string $mime): void
    {
        $fileContents = Storage::disk($this->attachment->disk)->get($path);
        $dataUri = "data:{$mime};base64,".base64_encode($fileContents);

        $response = Http::withToken($token)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Extract all text from this image and provide a brief description.'],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ],
                    ],
                ],
                'max_tokens' => 500,
            ]);

        if ($response->successful()) {
            $text = $response->json('choices.0.message.content');
            $this->attachment->update([
                'ai_transcript' => $text,
            ]);
        } else {
            throw new \Exception('Vision API failed: '.$response->body());
        }
    }

    protected function extractGeminiText(Response $response): string
    {
        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $content = trim((string) $content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json|text)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            $content = trim($content);
        }

        return $content;
    }

    protected function normalizedMime(?string $mime): string
    {
        $mime = strtolower(trim((string) $mime));

        return trim(explode(';', $mime)[0]);
    }

    protected function geminiAudioMime(string $mime): string
    {
        return match ($mime) {
            'audio/oga', 'audio/opus' => 'audio/ogg',
            'audio/x-wav' => 'audio/wav',
            'audio/mpeg', 'audio/mpga' => 'audio/mp3',
            'audio/x-m4a', 'audio/m4a' => 'audio/mp4',
            'audio/x-aac' => 'audio/aac',
            default => $mime,
        };
    }

    protected function finalize(): void
    {
        $this->attachment->update(['processing_status' => 'ready']);

        $conversation = $this->message->conversation;
        if ($conversation) {
            $attachmentCount = $this->message->attachments()->count();
            if ($attachmentCount > self::MAX_ATTACHMENTS_PER_MESSAGE) {
                Log::warning('Message attachment count exceeded processing guardrail', [
                    'message_id' => $this->message->id,
                    'attachment_count' => $attachmentCount,
                    'max_allowed' => self::MAX_ATTACHMENTS_PER_MESSAGE,
                ]);
            }

            $status = $this->message->processing_status ?? [];
            $status['media_downloaded'] = true;
            $this->message->update(['processing_status' => $status]);

            $awaitingVerification = ($this->message->metadata['awaiting_verification'] ?? false) === true;
            if ($awaitingVerification || ! empty($status['ai_analyzed'])) {
                return;
            }

            $session = $this->message->session;
            if ($session && $session->status === ConversationSessionStatus::AwaitingVerification) {
                return;
            }

            ProcessBufferedConversation::schedule($conversation);
        }
    }
}
