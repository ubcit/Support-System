<?php

namespace Modules\Communication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\Jobs\AnalyzeMessageThread;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Jobs\DownloadAttachment;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageChannel;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Services\ConversationService;
use Modules\Communication\Services\ProjectVerificationService;
use Modules\Customers\Services\CustomerAiBudgetService;
use Modules\Customers\Services\CustomerService;
use Modules\Employees\Models\Employee;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public string $channel = 'whatsapp'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CustomerService $customerService, ConversationService $conversationService): void
    {
        Log::info("Processing Incoming {$this->channel} Message", ['payload' => $this->payload]);

        if ($this->channel === 'whatsapp') {
            $this->processWhatsApp($customerService, $conversationService);
        } elseif ($this->channel === 'email') {
            $this->processEmail($customerService, $conversationService);
        }
    }

    protected function processWhatsApp(CustomerService $customerService, ConversationService $conversationService): void
    {
        $entries = $this->payload['entry'] ?? [];
        $verification = app(ProjectVerificationService::class);

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                $contacts = $value['contacts'] ?? [];
                $messages = $value['messages'] ?? [];

                if (empty($messages)) {
                    continue;
                }

                $messageData = $messages[0];
                $fromPhone = $messageData['from'] ?? null;
                $profileName = $contacts[0]['profile']['name'] ?? 'Unknown Sender';
                $messageId = $messageData['id'] ?? null;
                $type = $messageData['type'] ?? 'text'; // text, image, document, audio
                $aiControls = $this->extractAiControls($value);
                $simControls = $this->extractSimControls($value);

                if (! $fromPhone) {
                    continue;
                }

                // ROUTING 1: Boss Command Detection
                $employee = Employee::where('phone', $fromPhone)->first();
                $isBoss = $employee?->isBoss() ?? false;

                if ($isBoss) {
                    // Boss gets a short cooldown (default 1 min) and messages are treated as commands
                    $bossCustomer = $customerService->findOrCreateByPhone($fromPhone, [
                        'name' => $profileName,
                        'whatsapp_id' => $fromPhone,
                    ]);
                    $bossConversation = $conversationService->findOrCreateActive($bossCustomer, 'whatsapp');

                    $body = null;
                    if ($type === 'text') {
                        $body = $messageData['text']['body'] ?? null;
                    } elseif (in_array($type, ['image', 'document', 'audio', 'video'])) {
                        $body = $messageData[$type]['caption'] ?? "Media Received ({$type})";
                    }

                    $conversationService->addMessage($bossConversation, [
                        'channel' => MessageChannel::WhatsApp->value,
                        'direction' => MessageDirection::Inbound->value,
                        'sender_identifier' => $fromPhone,
                        'sender_name' => $profileName,
                        'subject' => 'Boss Command',
                        'body' => $body,
                        'raw_payload' => $value,
                        'customer_id' => $bossCustomer->id,
                        'status' => MessageStatus::Received->value,
                        'processing_status' => [
                            'media_downloaded' => ($type === 'text'),
                            'ai_analyzed' => false,
                            'issue_linked' => false,
                            'task_created' => false,
                        ],
                        'metadata' => array_merge([
                            'whatsapp_message_id' => $messageId,
                            'type' => $type,
                            'is_boss' => true,
                            'analyze_attachments' => $aiControls['analyze_attachments'],
                            'ai_token_budget' => $aiControls['ai_token_budget'],
                        ], $this->simMetadata($simControls)),
                    ]);

                    ProcessBufferedConversation::schedule(
                        $bossConversation,
                        true,
                        $simControls['cooldown_seconds'] ?? null,
                    );

                    continue;
                }

                $existingCustomer = $customerService->findByPhone($fromPhone);
                $needsVerification = ! $existingCustomer
                    || $verification->customerNeedsVerification($existingCustomer);

                if ($needsVerification) {
                    $this->processUnverifiedWhatsApp(
                        $customerService,
                        $conversationService,
                        $verification,
                        $existingCustomer,
                        $fromPhone,
                        $profileName,
                        $messageId,
                        $type,
                        $messageData,
                        $value,
                        $aiControls,
                        $simControls,
                    );

                    continue;
                }

                // Normal Customer Pipeline
                $customer = $existingCustomer;
                $conversation = $conversationService->findOrCreateActive($customer, 'whatsapp');

                $body = null;
                if ($type === 'text') {
                    $body = $messageData['text']['body'] ?? null;
                } elseif (in_array($type, ['image', 'document', 'audio', 'video'])) {
                    $body = $messageData[$type]['caption'] ?? "Media Received ({$type})";
                }

                $message = $conversationService->addMessage($conversation, [
                    'channel' => MessageChannel::WhatsApp->value,
                    'direction' => MessageDirection::Inbound->value,
                    'sender_identifier' => $fromPhone,
                    'sender_name' => $profileName,
                    'subject' => 'WhatsApp Message',
                    'body' => $body,
                    'raw_payload' => $value,
                    'customer_id' => $customer->id,
                    'status' => MessageStatus::Received->value,
                    'processing_status' => [
                        'media_downloaded' => false,
                        'ai_analyzed' => false,
                        'issue_linked' => false,
                        'task_created' => false,
                        'native_task_created' => false,
                    ],
                    'metadata' => array_merge([
                        'whatsapp_message_id' => $messageId,
                        'type' => $type,
                        'media_id' => $messageData[$type]['id'] ?? null,
                        'analyze_attachments' => $aiControls['analyze_attachments'],
                        'ai_token_budget' => $aiControls['ai_token_budget'],
                    ], $this->simMetadata($simControls)),
                ]);

                $session = $conversationService->latestSession($conversation);
                if ($type === 'text' && $session && $session->status === ConversationSessionStatus::Collecting && ! $session->project_id) {
                    $code = $verification->extractCodeCandidate($body);
                    $project = $code !== ''
                        ? $verification->findProjectByCode($code, $conversation->workspace_id)
                        : null;
                    if ($project && $this->looksLikeStandaloneCode($body, $code)) {
                        $verification->pinProjectOnSession($session, $project, $message);
                    }
                }

                if (in_array($type, ['image', 'document', 'audio', 'video'])) {
                    $this->queueMediaDownload($message, $messageData, $type);
                } else {
                    $status = $message->processing_status ?? [];
                    $status['media_downloaded'] = true;
                    $message->update(['processing_status' => $status]);

                    // Code-only pin messages should not trigger AI until a real request arrives.
                    if (! empty($message->metadata['project_code_pin'])) {
                        continue;
                    }

                    $budget = app(CustomerAiBudgetService::class);
                    if ($budget->isExhausted($customer)) {
                        $budget->divertToManualReview($conversation);
                    } else {
                        ProcessBufferedConversation::schedule(
                            $conversation,
                            false,
                            $simControls['cooldown_seconds'] ?? null,
                        );
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $messageData
     * @param  array<string, mixed>  $value
     * @param  array{analyze_attachments: bool, ai_token_budget: int}  $aiControls
     * @param  array{cooldown_seconds?: int, force_ai_error?: bool}  $simControls
     */
    protected function processUnverifiedWhatsApp(
        CustomerService $customerService,
        ConversationService $conversationService,
        ProjectVerificationService $verification,
        $existingCustomer,
        string $fromPhone,
        string $profileName,
        ?string $messageId,
        string $type,
        array $messageData,
        array $value,
        array $aiControls,
        array $simControls = [],
    ): void {
        $customer = $existingCustomer ?? $customerService->findOrCreateByPhone($fromPhone, [
            'name' => $profileName.' (Unknown)',
            'whatsapp_id' => $fromPhone,
            'metadata' => [
                'needs_project_verification' => true,
                'project_verified' => false,
            ],
        ]);

        if ($existingCustomer) {
            $meta = $customer->metadata ?? [];
            if (($meta['needs_project_verification'] ?? true) !== false) {
                $customer->update([
                    'metadata' => array_merge($meta, [
                        'needs_project_verification' => true,
                        'project_verified' => false,
                    ]),
                ]);
            }
        }

        $conversation = $conversationService->findOrCreateActive($customer, 'whatsapp');
        $session = $conversationService->latestSession($conversation);

        if (! $session || ! in_array($session->status, [
            ConversationSessionStatus::Collecting,
            ConversationSessionStatus::AwaitingVerification,
        ], true)) {
            $session = $conversationService->ensureCollectingSession($conversation);
        }

        $session->update([
            'status' => ConversationSessionStatus::AwaitingVerification,
            'title' => $session->title ?: 'Awaiting project code',
            'summary' => $session->summary ?: 'Waiting for the customer to verify with a project code.',
            'ended_at' => null,
            'needs_review' => false,
        ]);

        $body = null;
        if ($type === 'text') {
            $body = $messageData['text']['body'] ?? null;
        } elseif (in_array($type, ['image', 'document', 'audio', 'video'])) {
            $body = $messageData[$type]['caption'] ?? "Media Received ({$type})";
        }

        $message = $conversationService->addMessage($conversation, [
            'channel' => MessageChannel::WhatsApp->value,
            'direction' => MessageDirection::Inbound->value,
            'sender_identifier' => $fromPhone,
            'sender_name' => $profileName,
            'subject' => 'Unverified WhatsApp Message',
            'body' => $body,
            'raw_payload' => $value,
            'customer_id' => $customer->id,
            'status' => MessageStatus::Received->value,
            'processing_status' => [
                'media_downloaded' => false,
                'ai_analyzed' => true, // Hold AI until verified
                'issue_linked' => false,
                'task_created' => false,
                'native_task_created' => false,
            ],
            'metadata' => array_merge([
                'whatsapp_message_id' => $messageId,
                'type' => $type,
                'media_id' => $messageData[$type]['id'] ?? null,
                'awaiting_verification' => true,
                'analyze_attachments' => $aiControls['analyze_attachments'],
                'ai_token_budget' => $aiControls['ai_token_budget'],
            ], $this->simMetadata($simControls)),
        ]);

        if (in_array($type, ['image', 'document', 'audio', 'video'])) {
            $this->queueMediaDownload($message, $messageData, $type);
        } else {
            $status = $message->processing_status ?? [];
            $status['media_downloaded'] = true;
            $message->update(['processing_status' => $status]);
        }

        $code = $type === 'text' ? $verification->extractCodeCandidate($body) : '';
        $project = $code !== ''
            ? $verification->findProjectByCode($code, $conversation->workspace_id)
            : null;

        if ($project && $this->looksLikeStandaloneCode($body, $code)) {
            $verification->verifyAndContinue(
                $customer->fresh(),
                $conversation,
                $session->fresh(),
                $message->fresh(),
                $project,
                $simControls['cooldown_seconds'] ?? null,
            );

            return;
        }

        if ($type === 'text' && $code !== '' && $this->looksLikeStandaloneCode($body, $code)) {
            $verification->sendInvalidCodeReply($conversation, $session, $customer);
        }

        $verification->promptForCode($conversation, $session->fresh(), $customer);

        Log::info("Unverified number {$fromPhone} held for project code. Session {$session->uuid}.");
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    protected function queueMediaDownload($message, array $messageData, string $type): void
    {
        $mediaDataForType = $messageData[$type] ?? [];
        $mediaId = $mediaDataForType['id'] ?? null;
        $mimeType = $mediaDataForType['mime_type'] ?? 'application/octet-stream';

        $filename = $mediaDataForType['filename'] ?? null;
        $simulatedPath = $mediaDataForType['simulated_path'] ?? null;
        $simulatedDisk = $mediaDataForType['disk'] ?? 'local';

        $useSimulatedProvider = (bool) $simulatedPath && app()->environment(['local', 'testing']);
        $originalName = $filename ?: "whatsapp_{$type}_{$mediaId}";

        $attachment = $message->attachments()->create([
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => 0,
            'type' => AttachmentType::fromMimeType($mimeType)->value,
            'provider' => $useSimulatedProvider ? 'simulated' : 'whatsapp',
            'provider_media_id' => $mediaId,
            'provider_url' => $useSimulatedProvider
                ? (string) $simulatedPath
                : "https://graph.facebook.com/v19.0/{$mediaId}",
            'disk' => $useSimulatedProvider ? (string) $simulatedDisk : 'local',
            'processing_status' => 'pending',
        ]);

        DownloadAttachment::dispatch($attachment, $message);
    }

    protected function looksLikeStandaloneCode(?string $body, string $normalizedCode): bool
    {
        if ($normalizedCode === '' || $body === null) {
            return false;
        }

        $stripped = preg_replace('/[\s\-]+/', '', trim($body)) ?? '';

        return strcasecmp($stripped, $normalizedCode) === 0
            && strlen($normalizedCode) >= 4
            && strlen($normalizedCode) <= 16;
    }

    protected function processEmail(CustomerService $customerService, ConversationService $conversationService): void
    {
        // Assuming Mailgun inbound webhook payload structure
        $sender = $this->payload['sender'] ?? '';
        $fromName = $this->payload['from'] ?? 'Unknown Sender';
        $subject = $this->payload['subject'] ?? 'No Subject';
        $body = $this->payload['body-plain'] ?? '';

        if (empty($sender)) {
            return;
        }

        // Clean up from name (e.g. "John Doe <john@example.com>" -> "John Doe")
        if (preg_match('/^(.*?)\s*<.*>$/', $fromName, $matches)) {
            $fromName = trim($matches[1], '"\' ');
        }

        $aiControls = $this->extractAiControls($this->payload);

        // 1. Find or create the customer based on email
        $customer = $customerService->findOrCreateByEmail($sender, [
            'name' => $fromName,
            'email' => $sender,
        ]);

        // 2. Find active conversation or create a new one
        $conversation = $conversationService->findOrCreateActive($customer, 'email');

        // 3. Create the Message in the Conversation
        $message = $conversationService->addMessage($conversation, [
            'channel' => MessageChannel::Email->value,
            'direction' => MessageDirection::Inbound->value,
            'sender_identifier' => $sender,
            'sender_name' => $fromName,
            'subject' => $subject,
            'body' => $body,
            'raw_payload' => $this->payload,
            'customer_id' => $customer->id,
            'status' => MessageStatus::Received->value,
            'processing_status' => [
                'media_downloaded' => true, // Email attachments usually come inline or as URLs. Simplified for now.
                'ai_analyzed' => false,
                'issue_linked' => false,
                'task_created' => false,
                'native_task_created' => false,
            ],
            'metadata' => [
                'message_id' => $this->payload['Message-Id'] ?? null,
                'in_reply_to' => $this->payload['In-Reply-To'] ?? null,
                'references' => $this->payload['References'] ?? null,
                'analyze_attachments' => $aiControls['analyze_attachments'],
                'ai_token_budget' => $aiControls['ai_token_budget'],
            ],
        ]);

        // Trigger next step
        AnalyzeMessageThread::dispatch($conversation);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{analyze_attachments: bool, ai_token_budget: int}
     */
    protected function extractAiControls(array $source): array
    {
        $controls = $source['ai_controls'] ?? [];
        $analyzeAttachments = (bool) ($controls['analyze_attachments'] ?? false);
        $aiTokenBudget = (int) ($controls['ai_token_budget'] ?? 6000);

        return [
            'analyze_attachments' => $analyzeAttachments,
            'ai_token_budget' => max(500, $aiTokenBudget),
        ];
    }

    /**
     * Local/testing-only simulator controls embedded in webhook-shaped payloads.
     *
     * @param  array<string, mixed>  $source
     * @return array{cooldown_seconds?: int, force_ai_error?: bool, ai_provider?: string}
     */
    protected function extractSimControls(array $source): array
    {
        if (! app()->environment(['local', 'testing'])) {
            return [];
        }

        $controls = $source['sim_controls'] ?? [];
        if (! is_array($controls) || $controls === []) {
            return [];
        }

        $out = [];

        if (array_key_exists('cooldown_seconds', $controls)) {
            $out['cooldown_seconds'] = max(0, (int) $controls['cooldown_seconds']);
        }

        if (! empty($controls['force_ai_error'])) {
            $out['force_ai_error'] = true;
        }

        $provider = strtolower(trim((string) ($controls['ai_provider'] ?? '')));
        if (in_array($provider, ['mock', 'gemini', 'openai', 'groq'], true)) {
            $out['ai_provider'] = $provider;
        }

        return $out;
    }

    /**
     * @param  array{cooldown_seconds?: int, force_ai_error?: bool, ai_provider?: string}  $simControls
     * @return array<string, mixed>
     */
    protected function simMetadata(array $simControls): array
    {
        if ($simControls === []) {
            return [];
        }

        $meta = [];

        if (array_key_exists('cooldown_seconds', $simControls)) {
            $meta['sim_cooldown_seconds'] = $simControls['cooldown_seconds'];
        }

        if (! empty($simControls['force_ai_error'])) {
            $meta['force_ai_error'] = true;
        }

        if (! empty($simControls['ai_provider'])) {
            $meta['sim_ai_provider'] = $simControls['ai_provider'];
        }

        return $meta;
    }
}
