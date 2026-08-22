<?php

namespace Modules\Customers\Services;

use App\Models\AiRequestLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Customers\Models\Customer;
use Modules\MultiTenancy\Models\Workspace;

class CustomerAiBudgetService
{
    public const SKIP_REASON = 'daily_cost_limit_exceeded';

    public function workspaceDefaultLimit(): ?float
    {
        $raw = Workspace::query()->first()?->settings['default_daily_ai_cost_limit'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return (float) $raw;
    }

    public function effectiveLimit(Customer $customer): ?float
    {
        if ($customer->daily_ai_cost_limit !== null) {
            return (float) $customer->daily_ai_cost_limit;
        }

        return $this->workspaceDefaultLimit();
    }

    public function spentToday(Customer $customer, ?Carbon $now = null): float
    {
        $day = $now ?? now();

        return (float) AiRequestLog::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->sum('cost');
    }

    public function remaining(Customer $customer, ?Carbon $now = null): ?float
    {
        $limit = $this->effectiveLimit($customer);
        if ($limit === null) {
            return null;
        }

        return max(0, round($limit - $this->spentToday($customer, $now), 6));
    }

    public function isExhausted(Customer $customer, ?Carbon $now = null): bool
    {
        $limit = $this->effectiveLimit($customer);
        if ($limit === null) {
            return false;
        }

        return $this->spentToday($customer, $now) >= $limit;
    }

    public function divertToManualReview(Conversation $conversation, ?ConversationSession $session = null): ConversationSession
    {
        $session = $session ?? $this->collectingSession($conversation);

        $messages = $this->unanalyzedInboundMessages($conversation, $session);
        foreach ($messages as $message) {
            $status = $message->processing_status ?? [];
            $status['ai_analyzed'] = true;
            $message->update(['processing_status' => $status]);
        }

        $endedAt = $messages->last()?->created_at ?? now();
        $metadata = array_merge($session->metadata ?? [], [
            'skip_reason' => self::SKIP_REASON,
        ]);

        $session->update([
            'status' => ConversationSessionStatus::NeedsReview,
            'title' => 'Manual review (daily AI budget)',
            'summary' => 'Daily AI cost limit reached. New messages need manual verification.',
            'started_at' => $session->started_at ?? $messages->first()?->created_at ?? now(),
            'ended_at' => $endedAt,
            'last_message_at' => $endedAt,
            'auto_created' => false,
            'needs_review' => true,
            'metadata' => $metadata,
        ]);

        return $session->fresh();
    }

    public function collectingSession(Conversation $conversation): ConversationSession
    {
        $session = ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', ConversationSessionStatus::Collecting->value)
            ->latest('id')
            ->first();

        if ($session) {
            return $session;
        }

        $latest = ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        if ($latest) {
            return $latest;
        }

        return ConversationSession::create([
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Collecting,
            'started_at' => now(),
            'last_message_at' => now(),
            'task_ids' => [],
        ]);
    }

    /**
     * @return Collection<int, Message>
     */
    protected function unanalyzedInboundMessages(Conversation $conversation, ConversationSession $session): Collection
    {
        return $conversation->messages()
            ->where('direction', MessageDirection::Inbound->value)
            ->where('conversation_session_id', $session->id)
            ->where(function ($query) {
                $query->whereNull('processing_status->ai_analyzed')
                    ->orWhere('processing_status->ai_analyzed', false);
            })
            ->orderBy('created_at')
            ->get();
    }
}
