<?php

namespace Modules\Communication\Models;

use App\Helpers\TaskQuery;
use App\Models\AiRequestLog;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageChannel;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Support\CustomerAutoReplyMessages;
use Modules\Communication\Support\CustomerLocale;
use Modules\MultiTenancy\Traits\BelongsToWorkspace;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class ConversationSession extends Model
{
    use BelongsToWorkspace, HasUuid;

    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'project_id',
        'status',
        'customer_locale',
        'title',
        'summary',
        'started_at',
        'ended_at',
        'last_message_at',
        'last_read_at',
        'analysis',
        'request_log_id',
        'task_ids',
        'auto_created',
        'needs_review',
        'metadata',
    ];

    protected $casts = [
        'status' => ConversationSessionStatus::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_read_at' => 'datetime',
        'analysis' => 'array',
        'task_ids' => 'array',
        'auto_created' => 'boolean',
        'needs_review' => 'boolean',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(AiRequestLog::class, 'request_log_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        if ($this->status === ConversationSessionStatus::Collecting) {
            return 'New messages';
        }

        if ($this->status === ConversationSessionStatus::AwaitingVerification) {
            return 'Awaiting project code';
        }

        $preview = $this->relationLoaded('latestMessage')
            ? $this->latestMessage?->body
            : $this->messages()->orderBy('created_at')->value('body');

        $preview = trim((string) $preview);

        return $preview !== '' ? Str::limit($preview, 42) : 'Session';
    }

    public function isDone(): bool
    {
        return $this->status === ConversationSessionStatus::Done;
    }

    public function isCollecting(): bool
    {
        return $this->status === ConversationSessionStatus::Collecting;
    }

    public function isUnread(): bool
    {
        if ($this->status === ConversationSessionStatus::Done) {
            return false;
        }

        $latest = $this->relationLoaded('latestMessage')
            ? $this->latestMessage
            : $this->latestMessage()->first();

        if (! $latest) {
            return false;
        }

        $direction = $latest->direction instanceof MessageDirection
            ? $latest->direction
            : MessageDirection::tryFrom((string) $latest->direction);

        if ($direction !== MessageDirection::Inbound) {
            return false;
        }

        return $this->last_read_at === null || $latest->created_at->gt($this->last_read_at);
    }

    public function scopeUnread($query)
    {
        $latestDirection = '(SELECT direction FROM messages WHERE messages.conversation_session_id = conversation_sessions.id ORDER BY created_at DESC LIMIT 1)';
        $latestCreated = '(SELECT created_at FROM messages WHERE messages.conversation_session_id = conversation_sessions.id ORDER BY created_at DESC LIMIT 1)';

        return $query
            ->where('status', '!=', ConversationSessionStatus::Done->value)
            ->whereRaw($latestDirection.' = ?', ['inbound'])
            ->where(function ($q) use ($latestCreated) {
                $q->whereNull('last_read_at')
                    ->orWhereRaw($latestCreated.' > conversation_sessions.last_read_at');
            });
    }

    public function scopeAwaitingTaskReview($query)
    {
        $taskIds = Task::query()
            ->whereIn('current_state_id', TaskQuery::stateIdsForStatus('review'))
            ->whereNull('completed_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($taskIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('status', '!=', ConversationSessionStatus::Done->value)
            ->where(function ($inner) use ($taskIds) {
                foreach ($taskIds as $id) {
                    $inner->orWhereJsonContains('task_ids', $id)
                        ->orWhereJsonContains('task_ids', (string) $id);
                }
            });
    }

    public function scopeTab($query, string $tab)
    {
        return match ($tab) {
            'done', 'closed' => $query->where('status', ConversationSessionStatus::Done->value),
            'unread' => $query->unread(),
            'review' => $query->awaitingTaskReview(),
            default => $query,
        };
    }

    public function scopeSearchCustomers($query, string $search)
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        return $query->whereHas('conversation.customer', function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        });
    }

    /**
     * @return list<int>
     */
    public function taskIdList(): array
    {
        return array_values(array_filter(array_map('intval', $this->task_ids ?? [])));
    }

    /**
     * @param  list<int|string>  $taskIds
     * @return list<int>
     */
    public static function awaitingReviewTaskIds(array $taskIds): array
    {
        $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds))));
        if ($taskIds === []) {
            return [];
        }

        $reviewStateIds = TaskQuery::stateIdsForStatus('review');
        if ($reviewStateIds === []) {
            return [];
        }

        return Task::query()
            ->whereIn('id', $taskIds)
            ->whereIn('current_state_id', $reviewStateIds)
            ->whereNull('completed_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasTasksAwaitingReview(): bool
    {
        return self::awaitingReviewTaskIds($this->taskIdList()) !== [];
    }

    public function refreshStatusFromTasks(): void
    {
        $taskIds = $this->taskIdList();
        if ($taskIds === []) {
            $isActionable = (bool) (($this->analysis['is_actionable'] ?? $this->metadata['is_actionable'] ?? true));
            if (! $isActionable && $this->status !== ConversationSessionStatus::Collecting) {
                $this->update(['status' => ConversationSessionStatus::Done]);
            }

            return;
        }

        $tasks = Task::with('currentState')->whereIn('id', $taskIds)->get();
        if ($tasks->isEmpty()) {
            return;
        }

        $allDone = $tasks->every(fn (Task $task) => $task->isCompleted());
        $next = $allDone
            ? ConversationSessionStatus::Done
            : ($this->needs_review ? ConversationSessionStatus::NeedsReview : ConversationSessionStatus::Open);

        $wasDone = $this->status === ConversationSessionStatus::Done;

        if ($this->status !== $next) {
            $this->update(['status' => $next]);
        }

        if ($allDone && ! $wasDone && $next === ConversationSessionStatus::Done) {
            $this->sendCompletionAutoReply();
        }
    }

    protected function sendCompletionAutoReply(): void
    {
        $this->loadMissing(['conversation.customer', 'conversation.workspace', 'workspace']);

        $conversation = $this->conversation;
        $customer = $conversation?->customer;

        if (! $conversation || ! $customer) {
            return;
        }

        $workspace = $conversation->workspace ?? $this->workspace;
        if ($workspace && ($workspace->settings['auto_reply_on_completion'] ?? true) === false) {
            return;
        }

        $recipient = $customer->whatsapp_id ?? $customer->phone;
        if (! $recipient) {
            return;
        }

        $locale = CustomerLocale::forReply($this, $customer);
        $customerName = $customer->name ?? ($locale === CustomerLocale::AR ? 'عميلنا' : 'Customer');
        $body = CustomerAutoReplyMessages::body('session_completed', $locale, [
            'name' => $customerName,
        ]);

        $message = Message::create([
            'workspace_id' => $conversation->workspace_id,
            'channel' => MessageChannel::WhatsApp,
            'direction' => MessageDirection::Outbound,
            'sender_identifier' => config('services.whatsapp.phone_number_id'),
            'sender_name' => config('app.name', 'Support'),
            'recipient_identifier' => $recipient,
            'body' => $body,
            'conversation_id' => $conversation->id,
            'conversation_session_id' => $this->id,
            'customer_id' => $customer->id,
            'status' => MessageStatus::Processing,
            'metadata' => [
                'auto_reply' => true,
                'trigger' => 'session_completed',
            ],
        ]);

        SendOutboundMessage::dispatch($message);
    }

    public static function syncDoneForTask(Task $task): void
    {
        $id = (int) $task->id;
        $sessionId = (int) ($task->metadata['conversation_session_id'] ?? 0);

        static::query()
            ->where(function ($query) use ($id, $sessionId) {
                $query->whereJsonContains('task_ids', $id)
                    ->orWhereJsonContains('task_ids', (string) $id);

                if ($sessionId) {
                    $query->orWhere('id', $sessionId);
                }
            })
            ->get()
            ->filter(fn (self $session) => in_array($id, $session->taskIdList(), true))
            ->each(fn (self $session) => $session->refreshStatusFromTasks());
    }
}
