<?php

namespace Modules\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Modules\Communication\Services\WhatsAppCloudService;

class WhatsAppChannel
{
    public function __construct(
        protected WhatsAppCloudService $whatsAppService
    ) {}

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $messageData = $notification->toWhatsApp($notifiable);
        $to = $notifiable->routeNotificationFor('whatsapp') ?? $notifiable->whatsapp_id ?? $notifiable->phone;

        if (!$to) {
            return;
        }

        if (is_array($messageData) && isset($messageData['template'])) {
            $this->whatsAppService->sendTemplate(
                $to,
                $messageData['template'],
                $messageData['language'] ?? 'en_US',
                $messageData['components'] ?? []
            );
        } else {
            $text = is_array($messageData) ? ($messageData['text'] ?? '') : (string) $messageData;
            $this->whatsAppService->sendText($to, $text);
        }
    }
}
