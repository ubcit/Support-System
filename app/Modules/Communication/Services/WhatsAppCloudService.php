<?php

namespace Modules\Communication\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudService
{
    protected string $apiUrl;
    protected string $accessToken;
    protected string $phoneNumberId;
    protected ?string $lastError = null;

    public function __construct()
    {
        $version = config('services.whatsapp.version', 'v19.0');
        $this->apiUrl = "https://graph.facebook.com/{$version}/";
        $this->accessToken = config('services.whatsapp.access_token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Send a text message
     */
    public function sendText(string $to, string $text, ?string $replyToMessageId = null): array|null
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $text,
            ],
        ];

        if ($replyToMessageId) {
            $payload['context'] = [
                'message_id' => $replyToMessageId,
            ];
        }

        return $this->send($payload);
    }

    /**
     * Send a template message
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'en_US', array $components = []): array|null
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
                'components' => $components,
            ],
        ];

        return $this->send($payload);
    }

    /**
     * Mark a message as read
     */
    public function markAsRead(string $messageId): array|null
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        return $this->send($payload);
    }

    /**
     * Generic send method
     */
    protected function send(array $payload): array|null
    {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            Log::warning('WhatsAppCloudService: Missing token or phone_number_id. Message not sent.', $payload);
            return null;
        }

        $url = $this->apiUrl . $this->phoneNumberId . '/messages';

        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->post($url, $payload);

        if ($response->failed()) {
            $error = $response->json('error') ?? [];
            $code = $error['code'] ?? $response->status();
            $message = $error['message'] ?? $response->body();
            $this->lastError = "WhatsApp API {$code}: {$message}";
            Log::error('WhatsAppCloudService API Error: ' . $this->lastError);
            return null;
        }

        $this->lastError = null;
        return $response->json();
    }
}
