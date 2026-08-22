<?php

namespace Modules\Communication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Models\Message;
use Modules\Communication\Services\WhatsAppCloudService;

class SendOutboundMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public Message $message
    ) {}

    public function handle(WhatsAppCloudService $whatsAppService): void
    {
        if ($this->message->direction->value !== 'outbound') {
            return;
        }

        $customer = $this->message->customer;
        if (!$customer) {
            Log::error("Cannot send outbound message {$this->message->uuid}: No customer found.");
            $this->message->update(['status' => MessageStatus::Failed]);
            return;
        }

        if ($this->message->channel->value === 'whatsapp') {
            $this->sendWhatsApp($whatsAppService, $customer);
        } elseif ($this->message->channel->value === 'email') {
            $this->sendEmail($customer);
        }
    }

    protected function sendWhatsApp(WhatsAppCloudService $whatsAppService, $customer): void
    {
        $recipient = $customer->whatsapp_id ?? $customer->phone;
        
        if (!$recipient) {
            Log::error("Cannot send outbound message {$this->message->uuid}: No phone number or whatsapp_id found.");
            $this->message->update(['status' => MessageStatus::Failed]);
            return;
        }

        $response = $whatsAppService->sendText(
            to: $recipient,
            text: $this->message->body
        );

        if ($response) {
            $this->message->update([
                'status' => MessageStatus::Processed,
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'provider_message_id' => $response['messages'][0]['id'] ?? null,
                    'send_error' => null,
                ])
            ]);
        } else {
            $this->message->update([
                'status' => MessageStatus::Failed,
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'send_error' => $whatsAppService->lastError() ?? 'WhatsApp API rejected the message.',
                ]),
            ]);
        }
    }

    protected function sendEmail($customer): void
    {
        $recipient = $customer->email;
        if (!$recipient) {
            Log::error("Cannot send outbound message {$this->message->uuid}: No email found.");
            $this->message->update(['status' => MessageStatus::Failed]);
            return;
        }

        try {
            // Send standard Laravel Mail
            \Illuminate\Support\Facades\Mail::raw($this->message->body, function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject('Re: Customer Support Update');
            });

            $this->message->update([
                'status' => MessageStatus::Processed,
                'metadata' => array_merge($this->message->metadata ?? [], [
                    'provider' => 'smtp',
                ])
            ]);
        } catch (\Exception $e) {
            $this->message->update(['status' => MessageStatus::Failed]);
            Log::error("Failed to send email: " . $e->getMessage());
            throw $e;
        }
    }
}
