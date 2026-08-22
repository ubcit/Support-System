<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Models\ConversationSession;

class InboxCounts
{
    /**
     * Unread inbox count for the rail badge. Cheap, request-cached.
     *
     * Cached on the current HTTP request only so a later Livewire
     * request (or a test) does not keep a stale number after last_read_at.
     */
    public static function unread(): int
    {
        $key = 'inbox.unread';
        if (request()->attributes->has($key)) {
            return (int) request()->attributes->get($key);
        }

        $count = (int) ConversationSession::query()
            ->whereHas('conversation')
            ->unread()
            ->count();
        request()->attributes->set($key, $count);

        return $count;
    }

    /**
     * Drop request-local inbox caches after a thread is marked read.
     */
    public static function forget(): void
    {
        request()->attributes->remove('inbox.unread');

        foreach (array_keys(request()->attributes->all()) as $key) {
            if (is_string($key) && str_starts_with($key, 'inbox.panel.')) {
                request()->attributes->remove($key);
            }
        }
    }

    /**
     * Inbox secondary panel: filters + customers (sessions opened in main pane).
     *
     * @return array{
     *     all: int,
     *     unread: int,
     *     review: int,
     *     done: int,
     *     closed: int,
     *     tab: string,
     *     q: string,
     *     selected_id: int|null,
     *     selected_session_id: int|null,
     *     selected_customer_id: int|null,
     *     customers: list<array{
     *         key: string,
     *         id: int|null,
     *         name: string,
     *         phone: string|null,
     *         preview: string,
     *         time: string,
     *         unread_count: int,
     *         session_count: int,
     *         open: list<array{id: int, conversation_id: int, title: string, preview: string, time: string, unread: bool, done: bool, awaiting_review: bool, needs_review: bool, has_error: bool, collecting: bool}>,
     *         done: list<array{id: int, conversation_id: int, title: string, preview: string, time: string, unread: bool, done: bool, awaiting_review: bool, needs_review: bool, has_error: bool, collecting: bool}>
     *     }>
     * }
     */
    public static function panel(
        ?string $tab = null,
        ?string $q = null,
        ?int $selectedId = null,
        ?int $selectedSessionId = null,
        ?int $selectedCustomerId = null,
    ): array {
        $tab = $tab ?? request('tab', 'all');
        if ($tab === 'closed') {
            $tab = 'done';
        }
        if (! in_array($tab, ['all', 'unread', 'done', 'closed', 'review'], true)) {
            $tab = 'all';
        }

        $q = $q ?? trim((string) request('q', ''));
        $q = trim((string) $q);
        $cacheKey = 'inbox.panel.'.$tab.'.'.$q.'.'.($selectedSessionId ?? 's').'.'.($selectedId ?? 'c').'.'.($selectedCustomerId ?? 'cust');

        if (request()->attributes->has($cacheKey)) {
            return request()->attributes->get($cacheKey);
        }

        $query = ConversationSession::query()
            ->with(['conversation.customer', 'latestMessage'])
            ->whereHas('conversation')
            ->tab($tab)
            ->searchCustomers($q);

        if ($tab === 'all') {
            $query->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [ConversationSessionStatus::Done->value]);
        }

        $list = $query
            ->latest('last_message_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $reviewTaskIds = ConversationSession::awaitingReviewTaskIds(
            $list->flatMap(fn (ConversationSession $session) => $session->taskIdList())->all()
        );

        if ($selectedSessionId && ! $list->contains(fn (ConversationSession $session) => (int) $session->id === (int) $selectedSessionId)) {
            $selectedSessionId = null;
        }

        $customers = [];
        foreach ($list as $session) {
            $customerId = $session->conversation?->customer_id;
            $groupKey = $customerId !== null ? (string) $customerId : 'none-'.$session->conversation_id;
            if (! isset($customers[$groupKey])) {
                $customers[$groupKey] = [
                    'id' => $customerId,
                    'key' => $groupKey,
                    'name' => $session->conversation?->customer?->name ?? 'WhatsApp User',
                    'phone' => $session->conversation?->customer?->phone,
                    'preview' => '',
                    'time' => '',
                    'unread_count' => 0,
                    'session_count' => 0,
                    'open' => [],
                    'done' => [],
                ];
            }

            $hasError = ! empty($session->metadata['error']);
            $row = [
                'id' => (int) $session->id,
                'conversation_id' => (int) $session->conversation_id,
                'title' => $session->displayTitle(),
                'preview' => Str::limit((string) ($session->latestMessage?->body ?? 'No messages yet'), 42),
                'time' => $session->last_message_at?->format('H:i') ?? $session->updated_at?->format('H:i') ?? '',
                'unread' => $session->isUnread(),
                'done' => $session->isDone(),
                'awaiting_review' => $reviewTaskIds !== [] && array_intersect($session->taskIdList(), $reviewTaskIds) !== [],
                'needs_review' => (bool) $session->needs_review,
                'has_error' => $hasError,
                'collecting' => $session->isCollecting(),
            ];

            if ($customers[$groupKey]['preview'] === '') {
                $customers[$groupKey]['preview'] = $row['preview'];
                $customers[$groupKey]['time'] = $row['time'];
            }

            if ($row['unread']) {
                $customers[$groupKey]['unread_count']++;
            }
            $customers[$groupKey]['session_count']++;

            if ($row['done']) {
                $customers[$groupKey]['done'][] = $row;
            } else {
                $customers[$groupKey]['open'][] = $row;
            }
        }

        if ($selectedCustomerId === null && $selectedSessionId) {
            $selectedCustomerId = $list->firstWhere('id', $selectedSessionId)?->conversation?->customer_id;
        }

        if ($selectedCustomerId === null && $selectedId) {
            $selectedCustomerId = $list->first(
                fn (ConversationSession $session) => (int) $session->conversation_id === (int) $selectedId
            )?->conversation?->customer_id;
        }

        if ($selectedCustomerId !== null) {
            $selectedCustomerId = (int) $selectedCustomerId;
            $known = collect($customers)->contains(
                fn (array $group) => (int) ($group['id'] ?? 0) === $selectedCustomerId
            );
            if (! $known) {
                $selectedCustomerId = null;
            }
        }

        $countBase = ConversationSession::query()->whereHas('conversation');

        $payload = [
            'all' => (int) (clone $countBase)->count(),
            'unread' => static::unread(),
            'review' => (int) (clone $countBase)->awaitingTaskReview()->count(),
            'done' => (int) (clone $countBase)->where('status', ConversationSessionStatus::Done->value)->count(),
            'closed' => (int) (clone $countBase)->where('status', ConversationSessionStatus::Done->value)->count(),
            'tab' => $tab,
            'q' => $q,
            'selected_id' => $selectedSessionId ? (int) ($list->firstWhere('id', $selectedSessionId)?->conversation_id) : ($selectedId ? (int) $selectedId : null),
            'selected_session_id' => $selectedSessionId ? (int) $selectedSessionId : null,
            'selected_customer_id' => $selectedCustomerId,
            'customers' => array_values($customers),
        ];

        request()->attributes->set($cacheKey, $payload);

        return $payload;
    }
}
