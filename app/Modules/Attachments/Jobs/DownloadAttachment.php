<?php

namespace Modules\Attachments\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Models\Message;

class DownloadAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Attachment $attachment,
        public Message $message // Keep reference to update pipeline later
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Downloading attachment {$this->attachment->uuid}");

        $this->attachment->update(['processing_status' => 'downloading']);

        try {
            $url = $this->attachment->provider_url;
            $token = config('services.whatsapp.access_token');
            $contents = '';

            if ($this->attachment->provider === 'whatsapp') {
                // 1. Get the actual media URL
                $mediaMetaResponse = Http::withToken($token)->get($url);
                if ($mediaMetaResponse->failed()) {
                    throw new \Exception('Failed to retrieve media metadata from WhatsApp: ' . $mediaMetaResponse->body());
                }

                $mediaUrl = $mediaMetaResponse->json('url');
                if (!$mediaUrl) {
                    throw new \Exception('Media URL missing from WhatsApp metadata response');
                }

                // 2. Download the media
                $mediaResponse = Http::withToken($token)->get($mediaUrl);
                if ($mediaResponse->failed()) {
                    throw new \Exception('Failed to download media bytes from WhatsApp: ' . $mediaResponse->status());
                }

                $contents = $mediaResponse->body();
                
                // Set the correct original_name based on mime_type if it doesn't have an extension
                $mimeType = $mediaMetaResponse->json('mime_type');
                if ($mimeType) {
                    $this->attachment->update(['mime_type' => $mimeType]);
                    $extension = explode('/', $mimeType)[1] ?? 'bin';
                    if (!str_contains($this->attachment->original_name, '.')) {
                        $this->attachment->original_name .= '.' . $extension;
                    }
                }
            } elseif ($this->attachment->provider === 'simulated') {
                // Local-only branch for simulators/tests. `provider_url` stores the
                // Storage path to read bytes from.
                if (! app()->environment(['local', 'testing'])) {
                    throw new \Exception('Simulated attachments are only supported in local/testing.');
                }

                $contents = Storage::disk($this->attachment->disk)->get($url);
                if ($contents === null) {
                    throw new \Exception('Simulated attachment content not found for path: ' . $url);
                }

                // If the original filename has no extension, derive one from mime.
                $mimeType = $this->attachment->mime_type;
                if ($mimeType && !str_contains($this->attachment->original_name, '.')) {
                    $extension = explode('/', $mimeType)[1] ?? 'bin';
                    $this->attachment->original_name .= '.' . $extension;
                }
            } else {
                // generic fallback
                $response = Http::get($url);
                if ($response->failed()) {
                    throw new \Exception('Failed to download media from provider: ' . $response->status());
                }
                $contents = $response->body();
            }

            // Create hierarchical path: customers/{id}/conversations/{id}/{date}/original_name
            $customerId = $this->message->customer_id ?? 'unknown';
            $conversationId = $this->message->conversation_id;
            $date = now()->format('Y-m-d');
            $path = "customers/{$customerId}/conversations/{$conversationId}/{$date}/{$this->attachment->original_name}";

            Storage::disk($this->attachment->disk)->put($path, $contents);

            $this->attachment->update([
                'stored_path' => $path,
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'downloaded_at' => now(),
                'processing_status' => 'ready', // Or 'pending_analysis' if AI comes next
            ]);

            // Update Message Pipeline State
            $status = $this->message->processing_status ?? [];
            $status['media_downloaded'] = true;
            $this->message->update(['processing_status' => $status]);

            // Trigger next step in pipeline (Processing AI extractors)
            \Modules\Attachments\Jobs\ProcessAttachment::dispatch($this->attachment, $this->message);

        } catch (\Exception $e) {
            Log::error("Failed to download attachment {$this->attachment->uuid}: " . $e->getMessage());
            
            $this->attachment->update(['processing_status' => 'failed']);
            
            // Re-throw to trigger retry
            throw $e;
        }
    }
}
