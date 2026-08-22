<?php

namespace Modules\Communication\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailWebhookController extends Controller
{
    /**
     * Handle incoming Email Webhook payloads (e.g., from Mailgun or Postmark)
     */
    public function handle(Request $request): JsonResponse
    {
        // Example for Mailgun webhook signature verification
        // You should implement the actual signature verification based on your provider
        if (!$this->verifySignature($request)) {
            Log::warning('Email Webhook: Invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Dispatch to queue for processing
        \Modules\Communication\Jobs\ProcessIncomingMessage::dispatch(
            $request->all(),
            'email'
        );

        return response()->json(['status' => 'success']);
    }

    protected function verifySignature(Request $request): bool
    {
        $provider = config('services.email_inbound.provider', 'mailgun');
        
        if ($provider === 'mailgun') {
            $signingKey = config('services.mailgun.webhook_signing_key');
            if (empty($signingKey)) {
                return app()->environment('local', 'testing');
            }
            
            $signature = $request->input('signature');
            if (!$signature) return false;

            $timestamp = $signature['timestamp'] ?? '';
            $token = $signature['token'] ?? '';
            $sig = $signature['signature'] ?? '';

            $expected = hash_hmac('sha256', $timestamp . $token, $signingKey);
            return hash_equals($expected, $sig);
        }

        return app()->environment('local', 'testing');
    }
}
