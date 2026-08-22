<?php

namespace Modules\Communication\Services;

use Illuminate\Support\Facades\Log;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageChannel;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Support\CustomerAutoReplyMessages;
use Modules\Communication\Support\CustomerLocale;
use Modules\Customers\Models\Customer;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Projects\Support\ProjectCodeGenerator;

class ProjectVerificationService
{
    public function __construct(
        protected ProjectCodeGenerator $codes,
        protected ProjectService $projects,
        protected ConversationService $conversations,
    ) {}

    public function customerNeedsVerification(Customer $customer): bool
    {
        $meta = $customer->metadata ?? [];

        if (($meta['project_verified'] ?? false) === true) {
            return false;
        }

        if (($meta['needs_project_verification'] ?? false) === true) {
            return true;
        }

        // Legacy / auto-created unknown contacts before the verification flag existed.
        return str_contains((string) $customer->name, '(Unknown)');
    }

    public function findProjectByCode(?string $rawCode, ?int $workspaceId = null): ?Project
    {
        $code = $this->codes->normalize($rawCode);
        if ($code === '') {
            return null;
        }

        $query = Project::query()->where('code', $code);
        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->first();
    }

    public function extractCodeCandidate(?string $body): string
    {
        return $this->codes->normalize($body);
    }

    public function promptForCode(Conversation $conversation, ConversationSession $session, Customer $customer): void
    {
        $session = $session->fresh() ?? $session;
        $customer = $customer->fresh() ?? $customer;

        $alreadyPrompted = (bool) ($session->metadata['verification_prompted'] ?? false);
        if ($alreadyPrompted) {
            return;
        }

        $this->sendOutbound(
            $conversation,
            $session,
            $customer,
            CustomerAutoReplyMessages::body(
                'project_verification_prompt',
                CustomerLocale::forReply($session, $customer),
            ),
            'project_verification_prompt'
        );

        $session->update([
            'metadata' => array_merge($session->metadata ?? [], [
                'verification_prompted' => true,
            ]),
        ]);
    }

    public function sendInvalidCodeReply(Conversation $conversation, ConversationSession $session, Customer $customer): void
    {
        $session = $session->fresh() ?? $session;
        $customer = $customer->fresh() ?? $customer;

        $this->sendOutbound(
            $conversation,
            $session,
            $customer,
            CustomerAutoReplyMessages::body(
                'project_verification_invalid',
                CustomerLocale::forReply($session, $customer),
            ),
            'project_verification_invalid'
        );
    }

    public function sendVerifiedAwaitingRequestReply(Conversation $conversation, ConversationSession $session, Customer $customer, Project $project): void
    {
        $session = $session->fresh() ?? $session;
        $customer = $customer->fresh() ?? $customer;

        $this->sendOutbound(
            $conversation,
            $session,
            $customer,
            CustomerAutoReplyMessages::body(
                'project_verification_ok_awaiting_request',
                CustomerLocale::forReply($session, $customer),
                ['project' => $project->name],
            ),
            'project_verification_ok_awaiting_request'
        );
    }

    /**
     * Attach customer to project and either resume buffered analysis or wait for the real request.
     *
     * @return 'resumed'|'awaiting_request'
     */
    public function verifyAndContinue(
        Customer $customer,
        Conversation $conversation,
        ConversationSession $session,
        Message $codeMessage,
        Project $project,
        ?int $cooldownSeconds = null,
    ): string {
        $this->projects->attachCustomer($project, $customer);

        $customer->update([
            'metadata' => array_merge($customer->metadata ?? [], [
                'needs_project_verification' => false,
                'project_verified' => true,
                'verified_project_id' => $project->id,
            ]),
        ]);

        $session->update([
            'project_id' => $project->id,
            'metadata' => array_merge($session->metadata ?? [], [
                'verified_project_id' => $project->id,
                'verified_at' => now()->toIso8601String(),
            ]),
        ]);

        $status = $codeMessage->processing_status ?? [];
        $status['ai_analyzed'] = true;
        $status['issue_linked'] = true;
        $status['task_created'] = true;
        $codeMessage->update([
            'processing_status' => $status,
            'metadata' => array_merge($codeMessage->metadata ?? [], [
                'project_code_verification' => true,
                'verified_project_id' => $project->id,
            ]),
        ]);

        $priorInbound = $conversation->messages()
            ->where('conversation_session_id', $session->id)
            ->where('direction', MessageDirection::Inbound->value)
            ->where('id', '!=', $codeMessage->id)
            ->get();

        if ($priorInbound->isEmpty()) {
            $session->update([
                'status' => ConversationSessionStatus::Collecting,
                'title' => 'Verified — awaiting request',
                'summary' => "Linked to project {$project->name} ({$project->code}). Waiting for the customer request.",
                'ended_at' => null,
                'needs_review' => false,
            ]);

            $this->sendVerifiedAwaitingRequestReply($conversation, $session, $customer, $project);

            Log::info("ProjectVerification: Customer {$customer->id} verified for project {$project->id}; awaiting request.");

            return 'awaiting_request';
        }

        foreach ($priorInbound as $message) {
            $processing = $message->processing_status ?? [];
            $processing['ai_analyzed'] = false;
            $processing['issue_linked'] = false;
            $processing['task_created'] = false;
            $message->update([
                'processing_status' => $processing,
                'metadata' => array_merge($message->metadata ?? [], [
                    'awaiting_verification' => false,
                ]),
            ]);
        }

        $session->update([
            'status' => ConversationSessionStatus::Collecting,
            'title' => null,
            'summary' => null,
            'ended_at' => null,
            'needs_review' => false,
        ]);

        if ($cooldownSeconds === null && app()->environment(['local', 'testing'])) {
            $fromMeta = data_get($codeMessage->metadata, 'sim_cooldown_seconds');
            if ($fromMeta !== null) {
                $cooldownSeconds = max(0, (int) $fromMeta);
            }
        }

        ProcessBufferedConversation::schedule($conversation, false, $cooldownSeconds, $session->id);

        Log::info("ProjectVerification: Customer {$customer->id} verified for project {$project->id}; resuming buffer on prior messages.");

        return 'resumed';
    }

    /**
     * Pin a project onto a collecting session when a known customer sends only a code.
     */
    public function pinProjectOnSession(ConversationSession $session, Project $project, Message $codeMessage): void
    {
        $session->update([
            'project_id' => $project->id,
            'metadata' => array_merge($session->metadata ?? [], [
                'verified_project_id' => $project->id,
                'pinned_by_code' => true,
            ]),
        ]);

        $status = $codeMessage->processing_status ?? [];
        $status['ai_analyzed'] = true;
        $codeMessage->update([
            'processing_status' => $status,
            'metadata' => array_merge($codeMessage->metadata ?? [], [
                'project_code_pin' => true,
                'verified_project_id' => $project->id,
            ]),
        ]);
    }

    protected function sendOutbound(
        Conversation $conversation,
        ConversationSession $session,
        Customer $customer,
        string $body,
        string $trigger,
    ): void {
        $recipient = $customer->whatsapp_id ?? $customer->phone;
        if (! $recipient) {
            return;
        }

        $message = Message::create([
            'workspace_id' => $conversation->workspace_id,
            'channel' => MessageChannel::WhatsApp,
            'direction' => MessageDirection::Outbound,
            'sender_identifier' => config('services.whatsapp.phone_number_id'),
            'sender_name' => config('app.name', 'Support'),
            'recipient_identifier' => $recipient,
            'body' => $body,
            'conversation_id' => $conversation->id,
            'conversation_session_id' => $session->id,
            'customer_id' => $customer->id,
            'status' => MessageStatus::Processing,
            'metadata' => [
                'auto_reply' => true,
                'trigger' => $trigger,
            ],
        ]);

        SendOutboundMessage::dispatch($message);
    }
}
