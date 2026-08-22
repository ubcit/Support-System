<?php

namespace Modules\Communication\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Repositories\ConversationRepositoryInterface;
use Modules\Communication\Support\CustomerLocale;
use Modules\Customers\Models\Customer;
use Modules\Notifications\Services\NotificationService;

class ConversationService
{
    public function __construct(
        protected ConversationRepositoryInterface $repository
    ) {}

    public function list(array $filters = [], ?string $search = null, ?string $sortBy = 'last_message_at', string $direction = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->with(['customer', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->filter($filters)
            ->search($search) // Note: might need custom search on related messages/customers
            ->sort($sortBy, $direction);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): Conversation
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    /**
     * Find an active conversation for a customer on a specific channel, or create one.
     */
    public function findOrCreateActive(Customer $customer, string $channel = 'whatsapp'): Conversation
    {
        $conversation = $this->repository->newQuery()
            ->where('customer_id', $customer->id)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return $this->repository->create([
            'customer_id' => $customer->id,
            'channel' => $channel,
            'status' => 'active',
            'last_message_at' => now(),
        ]);
    }

    public function addMessage(Conversation $conversation, array $messageData): Message
    {
        $session = $this->sessionForNewMessage($conversation, $this->isInboundPayload($messageData));
        $messageData['conversation_session_id'] = $messageData['conversation_session_id'] ?? $session->id;

        $message = $conversation->messages()->create($messageData);

        $now = now();
        $this->repository->update($conversation->id, [
            'last_message_at' => $now,
        ]);
        $session->update([
            'last_message_at' => $now,
            'started_at' => $session->started_at ?? $message->created_at ?? $now,
        ]);

        if ($message->direction === MessageDirection::Inbound) {
            $conversation->loadMissing('customer');
            CustomerLocale::resolveForSession(
                $session->fresh(),
                $message->body,
                $conversation->customer,
            );
            app(NotificationService::class)->notifyConversationInbound($conversation, $message);
        }

        return $message->fresh();
    }

    public function ensureCollectingSession(Conversation $conversation): ConversationSession
    {
        return $this->sessionForNewMessage($conversation, inbound: true);
    }

    public function latestSession(Conversation $conversation): ?ConversationSession
    {
        return $conversation->sessions()->latest('id')->first();
    }

    protected function sessionForNewMessage(Conversation $conversation, bool $inbound): ConversationSession
    {
        $latest = $this->latestSession($conversation);

        if ($latest && in_array($latest->status, [
            ConversationSessionStatus::Collecting,
            ConversationSessionStatus::AwaitingVerification,
        ], true)) {
            return $latest;
        }

        if (! $inbound && $latest) {
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

    protected function isInboundPayload(array $messageData): bool
    {
        $direction = $messageData['direction'] ?? null;
        if ($direction instanceof MessageDirection) {
            return $direction === MessageDirection::Inbound;
        }

        return (string) $direction === MessageDirection::Inbound->value;
    }

    public function updateStatus(string $uuid, string $status): Conversation
    {
        $conversation = $this->repository->findByUuidOrFail($uuid);

        return $this->repository->update($conversation->id, ['status' => $status]);
    }
}
