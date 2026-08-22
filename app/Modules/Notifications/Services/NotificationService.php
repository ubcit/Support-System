<?php

namespace Modules\Notifications\Services;

use App\Helpers\InboxNav;
use Illuminate\Support\Str;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\Message;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Models\Notification;

class NotificationService
{
    public function send(
        string $title,
        string $body,
        string $type = 'general',
        ?Employee $employee = null,
        ?int $userId = null,
        string $channel = 'in_app',
        ?string $actionUrl = null,
        array $metadata = []
    ): Notification {
        return Notification::create([
            'employee_id' => $employee?->id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'channel' => $channel,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }

    public function notifyConversationInbound(Conversation $conversation, Message $message): void
    {
        if ($message->direction !== MessageDirection::Inbound) {
            return;
        }

        if (! empty($message->metadata['is_boss'])) {
            return;
        }

        $conversation->loadMissing('customer');
        $title = $conversation->customer?->name ?? 'WhatsApp User';
        $body = Str::limit(trim((string) $message->body) !== '' ? (string) $message->body : 'New inbound message', 120);
        $url = InboxNav::url(null, (int) $conversation->id, null, $message->conversation_session_id ? (int) $message->conversation_session_id : null);
        $metadata = [
            'conversation_id' => (int) $conversation->id,
            'conversation_session_id' => $message->conversation_session_id ? (int) $message->conversation_session_id : null,
        ];

        Employee::query()->whereNotNull('user_id')->each(function (Employee $employee) use ($title, $body, $url, $metadata, $conversation) {
            $existing = Notification::query()
                ->where('employee_id', $employee->id)
                ->where('type', 'conversation')
                ->whereNull('read_at')
                ->where(function ($query) use ($conversation) {
                    $query->where('metadata->conversation_id', $conversation->id)
                        ->orWhere('metadata->conversation_id', (string) $conversation->id);
                })
                ->first();

            if ($existing) {
                $existing->fill([
                    'title' => $title,
                    'body' => $body,
                    'action_url' => $url,
                ]);
                $existing->created_at = now();
                $existing->save();

                return;
            }

            $this->send(
                title: $title,
                body: $body,
                type: 'conversation',
                employee: $employee,
                userId: $employee->user_id,
                actionUrl: $url,
                metadata: $metadata,
            );
        });
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->update(['read_at' => now()]);

        return $notification;
    }

    public function markAllAsRead(Employee $employee): int
    {
        return Notification::where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadForEmployee(Employee $employee, int $limit = 20)
    {
        return Notification::where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
