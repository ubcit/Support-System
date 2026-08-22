<?php

namespace Modules\Communication\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Communication\Enums\MessageChannel;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Services\ConversationService;
use Modules\Customers\Services\CustomerService;

class WebhookController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
        protected ConversationService $conversationService
    ) {}

    /**
     * Handle incoming WhatsApp Webhook payloads (e.g. Meta Graph API or Twilio)
     *
     * Return type is a union because Meta's GET verification handshake must
     * echo back a raw text/plain `hub_challenge` body (not a JSON envelope),
     * while every other branch returns JSON. This used to be typed as
     * JsonResponse only, which meant the plain-text success path threw a
     * TypeError -- i.e. the one-time verification step every WhatsApp
     * Business integration must pass before it can receive any messages at
     * all was actually broken.
     */
    public function handleWhatsApp(Request $request): Response|JsonResponse
    {
        // Basic verification for Meta Webhooks
        if ($request->isMethod('get')) {
            $verifyToken = config('services.whatsapp.verify_token');
            if ($request->query('hub_verify_token') === $verifyToken) {
                return response($request->query('hub_challenge'))->header('Content-Type', 'text/plain');
            }
            return response()->json(['error' => 'Invalid verify token'], 403);
        }

        // Verify webhook signature (POST)
        if (! $this->verifySignature($request)) {
            Log::warning('WhatsApp Webhook: Invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 1. Immediately return 200 OK to WhatsApp
        // 2. Dispatch the processing to the Queue
        \Modules\Communication\Jobs\ProcessIncomingMessage::dispatch(
            $request->all(),
            'whatsapp'
        );

        return response()->json(['status' => 'success']);
    }

    protected function verifySignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');
        if (empty($appSecret)) {
            if (app()->environment('local', 'testing')) {
                Log::warning('WhatsApp Webhook: No app_secret configured. Skipping signature verification.');
                return true;
            }
            Log::error('WhatsApp Webhook: No app_secret configured in production. Rejecting request.');
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
