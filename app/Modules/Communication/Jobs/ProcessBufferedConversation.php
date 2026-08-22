<?php

namespace Modules\Communication\Jobs;

use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Services\AI\AIManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Services\SessionReadyNotificationService;
use Modules\Communication\Services\WorkCommunicationService;
use Modules\Customers\Services\CustomerAiBudgetService;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Events\TaskCreated;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskAutoAssignmentService;
use Modules\Workflows\Services\WorkflowManager;

/**
 * Delayed job that waits for the cooldown window to expire before triggering AI analysis.
 *
 * How it works:
 * 1. When a message arrives, this job is dispatched with a delay (5 min customer / 1 min boss).
 * 2. The conversation's `last_message_at` is recorded at dispatch time as `$scheduledAfter`.
 * 3. When the job fires, it checks if `last_message_at` has changed (new messages arrived).
 *    - If YES: The customer is still talking. Re-dispatch with a fresh delay.
 *    - If NO: The cooldown expired. Collect all un-analyzed messages and send to AI.
 */
class ProcessBufferedConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    private const BILINGUAL_OUTPUT_DIRECTIVE = <<<'TXT'
OUTPUT LANGUAGE RULE:
- For every user-facing text field in your JSON output, include both English and Arabic.
- Applies to: summary, title, and each task title/description.
- Use this exact format inside the same string:
  English: <english text>
  Arabic: <arabic translation>
- Keep both versions semantically equivalent and concise.
TXT;

    public function __construct(
        public Conversation $conversation,
        public string $scheduledAfter,
        public int $cooldownSeconds = 300,
        public bool $isBoss = false,
        public ?int $sessionId = null,
    ) {}

    public static function schedule(Conversation $conversation, bool $isBoss = false, ?int $cooldownSeconds = null, ?int $sessionId = null): void
    {
        $conversation->refresh();

        if ($cooldownSeconds === null) {
            $workspace = Workspace::first();
            $minutes = $isBoss
                ? (int) ($workspace?->settings['boss_cooldown_minutes'] ?? 1)
                : (int) ($workspace?->settings['customer_cooldown_minutes'] ?? 5);
            $cooldownSeconds = max(0, $minutes) * 60;
        }

        $sessionId = $sessionId ?? ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [
                ConversationSessionStatus::Collecting->value,
                ConversationSessionStatus::AwaitingVerification->value,
            ])
            ->latest('id')
            ->value('id');

        $sessionStamp = $sessionId
            ? ConversationSession::query()->where('id', $sessionId)->value('last_message_at')
            : null;
        $scheduledAfter = $sessionStamp
            ? Carbon::parse($sessionStamp)->toIso8601String()
            : ($conversation->last_message_at?->toIso8601String() ?? now()->toIso8601String());

        self::dispatch(
            $conversation,
            $scheduledAfter,
            $cooldownSeconds,
            $isBoss,
            $sessionId,
        )->delay(now()->addSeconds($cooldownSeconds));
    }

    public function handle(): void
    {
        $this->conversation->refresh();

        $clockSource = $this->sessionId
            ? ConversationSession::query()->find($this->sessionId)
            : $this->conversation;
        $currentLastMessage = $clockSource?->last_message_at?->toIso8601String();

        if ($currentLastMessage && $currentLastMessage !== $this->scheduledAfter) {
            Log::info("Buffer: New messages detected for Conversation {$this->conversation->uuid}. Re-scheduling cooldown ({$this->cooldownSeconds}s).");

            self::dispatch(
                $this->conversation,
                $currentLastMessage,
                $this->cooldownSeconds,
                $this->isBoss,
                $this->sessionId,
            )->delay(now()->addSeconds($this->cooldownSeconds));

            return;
        }

        $unanalyzedMessages = $this->conversation->messages()
            ->with('attachments')
            ->where('direction', 'inbound')
            ->where(function ($q) {
                $q->whereNull('processing_status->ai_analyzed')
                    ->orWhere('processing_status->ai_analyzed', false);
            })
            ->when($this->sessionId, fn ($q) => $q->where('conversation_session_id', $this->sessionId))
            ->orderBy('created_at')
            ->get();

        if ($unanalyzedMessages->isEmpty()) {
            Log::info("Buffer: No un-analyzed messages found for Conversation {$this->conversation->uuid}. Skipping.");

            return;
        }

        $mediaPending = $unanalyzedMessages->contains(function ($msg) {
            $status = $msg->processing_status ?? [];

            return empty($status['media_downloaded']) && $msg->attachments->isNotEmpty();
        });

        if ($mediaPending) {
            Log::info("Buffer: Media still downloading for Conversation {$this->conversation->uuid}. Re-scheduling.");
            self::dispatch(
                $this->conversation,
                $this->conversation->last_message_at?->toIso8601String() ?? now()->toIso8601String(),
                $this->cooldownSeconds,
                $this->isBoss,
                $this->sessionId,
            )->delay(now()->addSeconds(max(15, (int) floor($this->cooldownSeconds / 4))));

            return;
        }

        $session = $this->sessionForAnalysis($unanalyzedMessages);
        $aiPayload = $this->buildAiPayload($unanalyzedMessages);
        $combinedText = $aiPayload['combined_text'];
        $estimatedTokens = $this->estimateTokenCount($combinedText);
        $tokenBudget = $aiPayload['token_budget'];

        if ($estimatedTokens > $tokenBudget) {
            Log::warning("Buffer: Token budget exceeded for Conversation {$this->conversation->uuid}. Truncating AI input.", [
                'estimated_tokens' => $estimatedTokens,
                'token_budget' => $tokenBudget,
            ]);
            $combinedText = $this->truncateToTokenBudget($combinedText, $tokenBudget);
            $estimatedTokens = $this->estimateTokenCount($combinedText);
        }

        if (empty(trim($combinedText))) {
            Log::info("Buffer: All messages are empty/media for Conversation {$this->conversation->uuid}. Skipping AI.");
            $this->markMessagesAnalyzed($unanalyzedMessages);
            $this->completeSession($session, $unanalyzedMessages, [
                'status' => ConversationSessionStatus::Done,
                'title' => 'No action',
                'summary' => 'No text to analyze.',
                'analysis' => ['is_actionable' => false],
                'task_ids' => [],
                'auto_created' => false,
                'needs_review' => false,
            ]);

            return;
        }

        if (app()->environment(['local', 'testing'])
            && $unanalyzedMessages->contains(fn ($msg) => (bool) data_get($msg->metadata, 'force_ai_error'))) {
            Log::warning("Buffer: Forced AI error (sim_controls) for Conversation {$this->conversation->uuid}.");
            $this->markMessagesAnalyzed($unanalyzedMessages);
            $this->completeSession($session, $unanalyzedMessages, [
                'status' => ConversationSessionStatus::NeedsReview,
                'title' => 'AI Failed — Manual Triage Needed',
                'summary' => 'AI analysis failed: Simulated AI failure (Message Simulator).',
                'analysis' => null,
                'request_log_id' => null,
                'task_ids' => [],
                'auto_created' => false,
                'needs_review' => true,
                'metadata' => ['error' => 'Simulated AI failure (Message Simulator)'],
            ]);

            return;
        }

        $this->conversation->load('customer');
        $customer = $this->conversation->customer;

        if (! $this->isBoss && $customer && app(CustomerAiBudgetService::class)->isExhausted($customer)) {
            Log::info("Buffer: Daily AI budget exhausted for Conversation {$this->conversation->uuid}. Diverting to manual review.");
            app(CustomerAiBudgetService::class)->divertToManualReview($this->conversation, $session);

            return;
        }

        $activeProjects = Project::where('status', 'active')
            ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
            ->pluck('name')->toArray();

        $employees = Employee::where('is_available', true)
            ->pluck('name')->toArray();

        $projectHints = empty($activeProjects) ? 'None' : implode(', ', $activeProjects);
        $employeeHints = empty($employees) ? 'None' : implode(', ', $employees);

        $workspace = Workspace::first();
        $providerKey = $workspace?->settings['ai_provider'] ?? 'gemini';
        // Message Simulator (local/testing) can override the workspace AI provider per message.
        $simProvider = $unanalyzedMessages
            ->map(fn ($msg) => data_get($msg->metadata, 'sim_ai_provider'))
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->last();
        if (is_string($simProvider) && $simProvider !== '') {
            $providerKey = $simProvider;
        }
        $autoCreate = (bool) ($workspace?->settings['auto_create_tasks'] ?? true);
        $minConfidence = (float) ($workspace?->settings['auto_create_min_confidence'] ?? 0.70);

        $prompt = AiPrompt::with('schema')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if (! $prompt || ! $prompt->schema) {
            Log::error("Buffer: No active AI Prompt/Schema found. Cannot analyze Conversation {$this->conversation->uuid}.");

            return;
        }

        $model = AiModel::where('provider', $providerKey)
            ->where('is_active', true)
            ->first();

        if (! $model) {
            $model = AiModel::where('is_active', true)->first();
        }

        if (! $model) {
            Log::error("Buffer: No active AI Model found. Cannot analyze Conversation {$this->conversation->uuid}.");

            return;
        }

        $bossNotes = $this->isBoss
            ? 'This is a direct command from the boss. Always treat as actionable.'
            : data_get($customer?->metadata, 'boss_notes', 'None');
        $messageBody = $prompt->user_prompt_template ?? '{{message}}';
        $messageBody = str_replace('{{message}}', $combinedText, $messageBody);
        $messageBody = str_replace('{{boss_notes}}', $bossNotes, $messageBody);
        $messageBody = str_replace('{{projects}}', $projectHints, $messageBody);
        $messageBody = str_replace('{{employees}}', $employeeHints, $messageBody);
        $messageBody .= "\n\n".self::BILINGUAL_OUTPUT_DIRECTIVE;

        try {
            $manager = app(AIManager::class);
            Log::info("Buffer: AI payload composed for Conversation {$this->conversation->uuid}", [
                'message_count' => $aiPayload['message_count'],
                'voice_items_count' => $aiPayload['voice_items_count'],
                'attachment_count' => $aiPayload['attachment_count'],
                'attachments_analyzed' => $aiPayload['analyze_attachments'],
                'estimated_tokens' => $estimatedTokens,
                'token_budget' => $tokenBudget,
            ]);
            $aiRequestLog = $manager->execute(
                $messageBody,
                $model,
                $prompt,
                $prompt->schema,
                null,
                false,
                [
                    'customer_id' => $customer?->id,
                    'conversation_id' => $this->conversation->id,
                    'conversation_session_id' => $session->id,
                    'source' => 'conversation',
                ]
            );

            if ($aiRequestLog->validation_status !== 'passed') {
                Log::warning("Buffer: AI validation failed for Conversation {$this->conversation->uuid}: {$aiRequestLog->error_message}");
                $this->markMessagesAnalyzed($unanalyzedMessages);
                $this->completeSession($session, $unanalyzedMessages, [
                    'status' => ConversationSessionStatus::NeedsReview,
                    'title' => 'AI Failed — Manual Triage Needed',
                    'summary' => 'AI validation failed: '.Str::limit($aiRequestLog->error_message, 120),
                    'analysis' => null,
                    'request_log_id' => $aiRequestLog->id,
                    'task_ids' => [],
                    'auto_created' => false,
                    'needs_review' => true,
                    'metadata' => ['error' => $aiRequestLog->error_message],
                ]);

                return;
            }

            $aiOutput = is_array($aiRequestLog->parsed_json) ? $aiRequestLog->parsed_json : [];
            $confidence = (float) ($aiOutput['confidence'] ?? 0);
            $isActionable = (bool) ($aiOutput['is_actionable'] ?? false);
            $shouldAutoCreate = $autoCreate && $isActionable && $confidence >= $minConfidence;

            $taskIds = [];
            if ($shouldAutoCreate) {
                $taskIds = $this->createTasksFromAiOutput($aiOutput, $unanalyzedMessages, $combinedText, $session);
                $projectId = Task::query()->whereIn('id', $taskIds)->whereNotNull('project_id')->value('project_id');
                if ($projectId) {
                    $aiRequestLog->update(['project_id' => $projectId]);
                }
            }

            $needsReview = ! $shouldAutoCreate;
            $title = is_string($aiOutput['title'] ?? null)
                ? $aiOutput['title']
                : (is_string($aiOutput['tasks'][0]['title'] ?? null)
                    ? $aiOutput['tasks'][0]['title']
                    : (is_string($aiOutput['summary'] ?? null) ? $aiOutput['summary'] : null));

            $status = ConversationSessionStatus::NeedsReview;
            if ($shouldAutoCreate && ! empty($taskIds)) {
                $status = ConversationSessionStatus::Open;
            } elseif (! $isActionable) {
                $status = ConversationSessionStatus::Done;
                $title = $title ?: 'No action';
            }

            $this->markMessagesAnalyzed($unanalyzedMessages);

            $this->conversation->refresh();
            $metadata = $this->conversation->metadata ?? [];
            $aiAnalyses = $metadata['ai_analyses'] ?? [];
            $aiAnalyses[] = [
                'provider' => $model->provider,
                'model' => $model->name,
                'timestamp' => now()->toIso8601String(),
                'result' => $aiOutput,
                'request_log_id' => $aiRequestLog->id,
                'task_ids' => $taskIds,
                'needs_review' => $needsReview,
            ];
            $metadata['ai_analyses'] = $aiAnalyses;
            $metadata['needs_review'] = $needsReview;
            $this->conversation->update(['metadata' => $metadata]);

            $this->completeSession($session, $unanalyzedMessages, [
                'status' => $status,
                'title' => $title,
                'summary' => is_string($aiOutput['summary'] ?? null) ? $aiOutput['summary'] : $title,
                'analysis' => $aiOutput,
                'request_log_id' => $aiRequestLog->id,
                'task_ids' => $taskIds,
                'auto_created' => $shouldAutoCreate && ! empty($taskIds),
                'needs_review' => $needsReview,
                'metadata' => ['confidence' => $confidence, 'is_actionable' => $isActionable],
            ]);

            Log::info("Buffer: AI analysis complete for Conversation {$this->conversation->uuid}. Auto-created: ".count($taskIds));
        } catch (\Exception $e) {
            Log::error("Buffer: AI analysis failed for Conversation {$this->conversation->uuid}: ".$e->getMessage());
            $this->markMessagesAnalyzed($unanalyzedMessages);
            $this->completeSession($session, $unanalyzedMessages, [
                'status' => ConversationSessionStatus::NeedsReview,
                'title' => 'AI Failed — Manual Triage Needed',
                'summary' => 'AI analysis failed: '.Str::limit($e->getMessage(), 120),
                'analysis' => null,
                'request_log_id' => null,
                'task_ids' => [],
                'auto_created' => false,
                'needs_review' => true,
                'metadata' => ['error' => $e->getMessage()],
            ]);
        }
    }

    protected function buildBurstText(Collection $messages): string
    {
        return $this->buildAiPayload($messages)['combined_text'];
    }

    /**
     * @return array{
     *     combined_text: string,
     *     message_count: int,
     *     voice_items_count: int,
     *     attachment_count: int,
     *     analyze_attachments: bool,
     *     token_budget: int
     * }
     */
    protected function buildAiPayload(Collection $messages): array
    {
        $analyzeAttachments = (bool) $messages->contains(function ($message) {
            return (bool) data_get($message->metadata, 'analyze_attachments', false);
        });
        $tokenBudget = (int) $messages->map(function ($message) {
            return (int) data_get($message->metadata, 'ai_token_budget', 6000);
        })->filter(fn ($budget) => $budget > 0)->min();
        $tokenBudget = max(500, $tokenBudget ?: 6000);
        $voiceItemsCount = 0;
        $attachmentCount = 0;

        $combinedText = $messages->map(function ($msg) use ($analyzeAttachments, &$voiceItemsCount, &$attachmentCount) {
            $parts = [];
            if (! empty($msg->body)) {
                $parts[] = $msg->body;
            }
            foreach ($msg->attachments as $attachment) {
                $attachmentCount++;
                if (! empty($attachment->ai_transcript)) {
                    if ($attachment->type === AttachmentType::Voice) {
                        $voiceItemsCount++;
                        $parts[] = '[voice transcript] '.$attachment->ai_transcript;
                    } elseif ($analyzeAttachments) {
                        $parts[] = '['.($attachment->original_name ?? 'attachment').' transcript] '.$attachment->ai_transcript;
                    }
                } elseif (! empty($attachment->original_name)) {
                    if ($analyzeAttachments) {
                        $parts[] = '[attachment] '.$attachment->original_name;
                    }
                }
            }

            return implode("\n", $parts);
        })->filter()->implode("\n\n");

        return [
            'combined_text' => $combinedText,
            'message_count' => $messages->count(),
            'voice_items_count' => $voiceItemsCount,
            'attachment_count' => $attachmentCount,
            'analyze_attachments' => $analyzeAttachments,
            'token_budget' => $tokenBudget,
        ];
    }

    protected function estimateTokenCount(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    protected function truncateToTokenBudget(string $text, int $tokenBudget): string
    {
        $maxChars = max(200, $tokenBudget * 4);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars)."\n\n[truncated: token budget applied]";
    }

    protected function markMessagesAnalyzed($messages): void
    {
        foreach ($messages as $msg) {
            $status = $msg->processing_status ?? [];
            $status['ai_analyzed'] = true;
            $msg->update(['processing_status' => $status]);
        }
    }

    protected function sessionForAnalysis(Collection $messages): ConversationSession
    {
        $sessionIds = $messages->pluck('conversation_session_id')->filter()->unique();
        if ($sessionIds->count() === 1) {
            $session = ConversationSession::find($sessionIds->first());
            if ($session) {
                $this->attachMessagesToSession($session, $messages);

                return $session;
            }
        }

        $session = ConversationSession::query()
            ->where('conversation_id', $this->conversation->id)
            ->where('status', ConversationSessionStatus::Collecting->value)
            ->latest('id')
            ->first();

        if (! $session) {
            $session = ConversationSession::create([
                'conversation_id' => $this->conversation->id,
                'status' => ConversationSessionStatus::Collecting,
                'started_at' => $messages->first()?->created_at ?? now(),
                'last_message_at' => $messages->last()?->created_at ?? now(),
                'task_ids' => [],
            ]);
        }

        $this->attachMessagesToSession($session, $messages);

        return $session;
    }

    protected function attachMessagesToSession(ConversationSession $session, Collection $messages): void
    {
        $ids = $messages->filter(fn ($message) => (int) $message->conversation_session_id !== (int) $session->id)
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            $this->conversation->messages()->whereIn('id', $ids)->update([
                'conversation_session_id' => $session->id,
            ]);
            $messages->each(function ($message) use ($session) {
                $message->conversation_session_id = $session->id;
            });
        }
    }

    /**
     * @param  array{
     *     status: ConversationSessionStatus,
     *     title?: ?string,
     *     summary?: ?string,
     *     analysis?: ?array,
     *     request_log_id?: ?int,
     *     task_ids?: list<int>,
     *     auto_created?: bool,
     *     needs_review?: bool,
     *     metadata?: array
     * }  $payload
     */
    protected function completeSession(ConversationSession $session, Collection $messages, array $payload): void
    {
        $endedAt = $messages->last()?->created_at ?? now();
        $metadata = array_merge($session->metadata ?? [], $payload['metadata'] ?? []);

        $session->update([
            'status' => $payload['status'],
            'title' => $payload['title'] ?? $session->title,
            'summary' => $payload['summary'] ?? $session->summary,
            'started_at' => $session->started_at ?? $messages->first()?->created_at,
            'ended_at' => $endedAt,
            'last_message_at' => $endedAt,
            'analysis' => $payload['analysis'] ?? $session->analysis,
            'request_log_id' => $payload['request_log_id'] ?? $session->request_log_id,
            'task_ids' => $payload['task_ids'] ?? $session->task_ids ?? [],
            'auto_created' => $payload['auto_created'] ?? false,
            'needs_review' => $payload['needs_review'] ?? false,
            'metadata' => $metadata,
        ]);

        $this->appendLegacySessionJson($session, $messages);
        $session->refreshStatusFromTasks();

        if (! $this->isBoss) {
            app(SessionReadyNotificationService::class)->notifyAfterSessionComplete($session->fresh(), $this->isBoss);
        }
    }

    protected function appendLegacySessionJson(ConversationSession $session, Collection $messages): void
    {
        $this->conversation->refresh();
        $metadata = $this->conversation->metadata ?? [];
        $sessions = $metadata['sessions'] ?? [];
        $sessions[] = [
            'id' => $session->id,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'message_ids' => $messages->pluck('id')->all(),
            'analysis' => $session->analysis,
            'request_log_id' => $session->request_log_id,
            'task_ids' => $session->taskIdList(),
            'auto_created' => $session->auto_created,
            'needs_review' => $session->needs_review,
            'error' => $session->metadata['error'] ?? null,
            'confidence' => $session->metadata['confidence'] ?? ($session->analysis['confidence'] ?? null),
        ];
        $metadata['sessions'] = $sessions;
        $this->conversation->update(['metadata' => $metadata]);
    }

    /**
     * @return list<int>
     */
    protected function createTasksFromAiOutput(array $aiOutput, Collection $burstMessages, string $combinedText, ConversationSession $session): array
    {
        $tasks = $aiOutput['tasks'] ?? [];
        if (empty($tasks)) {
            return [];
        }

        $workflowManager = app(WorkflowManager::class);
        $initialState = $workflowManager->getDefaultState('task');

        $project = app(ProjectService::class)
            ->matchForCustomer(
                $this->conversation->customer_id,
                $aiOutput['project'] ?? null,
                $session->project_id,
            );

        $sourceMessageIds = $burstMessages->pluck('id')->all();
        $packet = $this->customerPacketDescription($aiOutput['summary'] ?? '', $combinedText);
        $createdIds = [];

        foreach ($tasks as $taskData) {
            $task = Task::create([
                'project_id' => $project?->id,
                'type' => 'task',
                'title' => $taskData['title'] ?? 'New Task',
                'summary' => Str::limit($aiOutput['summary'] ?? ($taskData['description'] ?? ''), 100),
                'description' => $this->taskDescription($taskData['description'] ?? '', $packet),
                'workflow_id' => $initialState?->workflow_id,
                'current_state_id' => $initialState?->id,
                'priority' => $taskData['priority'] ?? 'medium',
                'sync_status' => 'queued',
                'metadata' => [
                    'source' => $this->isBoss ? 'boss_command' : 'customer_message',
                    'conversation_id' => $this->conversation->id,
                    'conversation_session_id' => $session->id,
                    'customer_id' => $this->conversation->customer_id,
                    'source_message_ids' => $sourceMessageIds,
                    'ai_confidence' => $aiOutput['confidence'] ?? 0,
                ],
            ]);

            $this->linkBurstAttachments($task, $burstMessages);

            $assignedTo = $taskData['assigned_to'] ?? '';
            TaskAutoAssignmentService::assignTask($task, $assignedTo);

            $commsService = app(WorkCommunicationService::class);
            $commsService->logSystemEvent($task, 'system_event', "Task created from {$this->conversation->channel} conversation.", [
                'action' => 'created',
                'source' => $this->isBoss ? 'boss_command' : 'customer_message',
            ]);

            TaskCreated::dispatch($task);
            $createdIds[] = $task->id;
        }

        return $createdIds;
    }

    protected function customerPacketDescription(string $summary, string $combinedText): string
    {
        $lines = [];
        if ($summary !== '') {
            $lines[] = '## AI summary';
            $lines[] = $summary;
            $lines[] = '';
        }
        $lines[] = '## Customer request';
        $lines[] = $combinedText;

        return implode("\n", $lines);
    }

    protected function taskDescription(string $aiDescription, string $packet): string
    {
        $parts = [];
        if ($aiDescription !== '') {
            $parts[] = $aiDescription;
        }
        $parts[] = $packet;

        return implode("\n\n", $parts);
    }

    protected function linkBurstAttachments(Task $task, Collection $burstMessages): void
    {
        foreach ($burstMessages as $message) {
            foreach ($message->attachments as $source) {
                if (empty($source->stored_path)) {
                    continue;
                }

                $task->attachments()->create([
                    'original_name' => $source->original_name,
                    'stored_path' => $source->stored_path,
                    'disk' => $source->disk ?? 'local',
                    'mime_type' => $source->mime_type,
                    'size_bytes' => $source->size_bytes ?? 0,
                    'type' => $source->type instanceof AttachmentType
                        ? $source->type->value
                        : ($source->type ?? 'other'),
                    'sha256' => $source->sha256,
                    'ai_transcript' => $source->ai_transcript,
                    'processing_status' => $source->processing_status ?? 'ready',
                    'metadata' => [
                        'linked_from_attachment_id' => $source->id,
                        'source_message_id' => $message->id,
                    ],
                ]);
            }
        }
    }
}
