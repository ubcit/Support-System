<?php

namespace App\Livewire\MessageSimulator;

use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Jobs\ProcessIncomingMessage;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Support\WhatsAppInboundPayload;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerService;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Security\Models\Role;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;

class Index extends Component
{
    use WithFileUploads;

    public ?int $certification_test_id = null;

    public string $ai_provider = 'gemini';

    public string $sync_provider = 'native';

    public bool $chaos_mode = false;

    public string $workspace = '';

    public string $project = '';

    public string $customer_phone = '+9647701234567';

    public string $sender_name = 'Test Customer';

    public string $boss_notes = 'Assign to Ahmed';

    public string $customer_message = 'The invoice page crashes when I click save.';

    /** @var 'unknown'|'known'|'boss' */
    public string $sender_mode = 'known';

    public bool $instant_ai = true;

    public bool $force_ai_error = false;

    public string $active_scenario = '';

    public bool $show_legacy = false;

    public $attachments = [];

    public ?array $e2eResults = null;

    public ?\App\Models\PipelineLog $pipelineLog = null;

    public ?array $workflowResults = null;

    public ?string $last_dispatched_phone = null;

    protected $rules = [
        'customer_phone' => 'required|string',
        'sender_name' => 'required|string',
        'customer_message' => 'nullable|string',
        'attachments.*' => 'nullable|file',
    ];

    public function mount(): void
    {
        $this->workspace = Workspace::first()?->name ?? 'Space Workspace';
        $this->instant_ai = app()->environment(['local', 'testing']);
        // Local/testing defaults to mock so full demos work without live Gemini/WhatsApp credentials.
        // Production workspace settings still use Gemini for the real webhook path.
        $this->ai_provider = app()->environment(['local', 'testing']) ? 'mock' : 'gemini';
    }

    public function updatedCertificationTestId($value): void
    {
        if ($value) {
            $test = \App\Models\CertificationTest::with('input')->find($value);
            if ($test && $test->input) {
                $this->customer_phone = $test->input->customer_phone ?? $this->customer_phone;
                $this->project = $test->input->project_name ?? '';
                $this->boss_notes = $test->input->boss_notes ?? $this->boss_notes;
                $this->customer_message = is_array($test->input->messages)
                    ? (array_is_list($test->input->messages)
                        ? implode("\n", array_map(fn ($line) => is_scalar($line) ? (string) $line : json_encode($line), $test->input->messages))
                        : (json_encode($test->input->messages) ?: $this->customer_message))
                    : ($test->input->messages ?? $this->customer_message);
            }
        }
    }

    public function loadScenario(string $key): void
    {
        $this->active_scenario = $key;
        $this->force_ai_error = false;
        $this->attachments = [];
        $this->e2eResults = null;
        $this->pipelineLog = null;

        $project = $this->resolveSelectedProject() ?? Project::query()->where('status', 'active')->orderBy('id')->first();
        $projectLabel = $project?->name ?? 'the project';
        $projectCode = $project?->code ?? 'XXXXXX';

        match ($key) {
            'unknown' => $this->applyScenario([
                'sender_mode' => 'unknown',
                'customer_phone' => $this->freshPhone(),
                'sender_name' => 'Unknown Caller',
                'boss_notes' => '',
                'customer_message' => 'Hello, I need help with a login error on the portal.',
                'project' => $project?->name ?? '',
            ]),
            'project_code' => $this->applyScenario([
                'sender_mode' => 'unknown',
                'customer_phone' => $this->last_dispatched_phone ?: $this->freshPhone(),
                'sender_name' => 'Unknown Caller',
                'boss_notes' => '',
                'customer_message' => $projectCode,
                'project' => $project?->name ?? '',
            ]),
            'known' => $this->applyScenario([
                'sender_mode' => 'known',
                'customer_phone' => '+9647701234567',
                'sender_name' => 'Known Customer',
                'boss_notes' => "Assign to Ahmed. This is for {$projectLabel}.",
                'customer_message' => "The invoice page crashes when I click save on {$projectLabel}.",
                'project' => $project?->name ?? '',
            ]),
            'boss' => $this->applyScenario([
                'sender_mode' => 'boss',
                'customer_phone' => $this->resolveBossPhone(),
                'sender_name' => 'Boss',
                'boss_notes' => '',
                'customer_message' => "Create an urgent task for {$projectLabel}: fix the payment gateway timeout.",
                'project' => $project?->name ?? '',
            ]),
            'task_created' => $this->applyScenario([
                'sender_mode' => 'known',
                'customer_phone' => '+9647701234567',
                'sender_name' => 'Known Customer',
                'boss_notes' => "Assign to Ahmed for {$projectLabel}.",
                'customer_message' => "Please fix the broken checkout button on {$projectLabel} — customers cannot pay.",
                'project' => $project?->name ?? '',
            ]),
            'ai_error' => $this->applyScenario([
                'sender_mode' => 'known',
                'customer_phone' => '+9647701234568',
                'sender_name' => 'AI Error Test',
                'boss_notes' => 'None',
                'customer_message' => 'This should fail AI and land in Needs Review.',
                'project' => $project?->name ?? '',
                'force_ai_error' => true,
            ]),
            'boss_notes_project' => $this->applyScenario([
                'sender_mode' => 'known',
                'customer_phone' => '+9647701234567',
                'sender_name' => 'Known Customer',
                'boss_notes' => "Priority: high. Assign to Sara. Project: {$projectLabel} (code {$projectCode}).",
                'customer_message' => "Need a QA pass on the new homepage for {$projectLabel}.",
                'project' => $project?->name ?? '',
            ]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyScenario(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    protected function freshPhone(): string
    {
        return '+96477'.substr((string) time(), -8);
    }

    protected function resolveBossPhone(): string
    {
        $boss = Employee::query()
            ->where(function ($q) {
                $q->where('role', 'like', '%boss%')
                    ->orWhereHas('roles', fn ($r) => $r->where('slug', Role::BOSS));
            })
            ->whereNotNull('phone')
            ->first();

        return $boss?->phone ?: '+9647700000000';
    }

    protected function resolveSelectedProject(): ?Project
    {
        if ($this->project === '') {
            return null;
        }

        return Project::query()
            ->where(function ($q) {
                $q->where('name', $this->project)
                    ->orWhere('code', $this->project);
            })
            ->first();
    }

    /**
     * @return array{cooldown_seconds?: int, force_ai_error?: bool, ai_provider?: string}
     */
    protected function simControls(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            return [];
        }

        $controls = [];

        if ($this->instant_ai) {
            $controls['cooldown_seconds'] = 0;
        }

        if ($this->force_ai_error) {
            $controls['force_ai_error'] = true;
        }

        $provider = strtolower(trim($this->ai_provider));
        if (in_array($provider, ['mock', 'gemini', 'openai', 'groq'], true)) {
            $controls['ai_provider'] = $provider;
        }

        return $controls;
    }

    protected function prepareCustomerForWorkflow(): void
    {
        /** @var CustomerService $customerService */
        $customerService = app(CustomerService::class);
        $phone = $this->customer_phone;
        $project = $this->resolveSelectedProject();

        if ($this->sender_mode === 'unknown') {
            $existing = $customerService->findByPhone($phone);
            if ($existing) {
                $existing->update([
                    'name' => str_contains((string) $existing->name, '(Unknown)')
                        ? $existing->name
                        : $this->sender_name.' (Unknown)',
                    'metadata' => array_merge($existing->metadata ?? [], [
                        'needs_project_verification' => true,
                        'project_verified' => false,
                        'boss_notes' => $this->boss_notes !== '' ? $this->boss_notes : 'None',
                    ]),
                ]);
            }

            return;
        }

        if ($this->sender_mode === 'boss') {
            $this->ensureBossEmployee($phone);

            return;
        }

        // known verified customer
        $customer = $customerService->findOrCreateByPhone($phone, [
            'name' => $this->sender_name,
            'whatsapp_id' => $phone,
            'daily_ai_cost_limit' => 100,
            'metadata' => [
                'needs_project_verification' => false,
                'project_verified' => true,
                'boss_notes' => $this->boss_notes !== '' ? $this->boss_notes : 'None',
            ],
        ]);

        $meta = array_merge($customer->metadata ?? [], [
            'needs_project_verification' => false,
            'project_verified' => true,
            'boss_notes' => $this->boss_notes !== '' ? $this->boss_notes : 'None',
        ]);

        $customer->update([
            'name' => $this->sender_name,
            'whatsapp_id' => $phone,
            'metadata' => $meta,
        ]);

        if ($project) {
            app(ProjectService::class)->attachCustomer($project, $customer);
        }
    }

    protected function ensureBossEmployee(string $phone): void
    {
        $employee = Employee::where('phone', $phone)->first();
        if ($employee && $employee->isBoss()) {
            return;
        }

        $bossRole = Role::where('slug', Role::BOSS)->first();
        $workspace = Workspace::first();

        if ($employee) {
            $employee->update(['role' => 'Executive Boss']);
            if ($bossRole) {
                $employee->roles()->syncWithoutDetaching([$bossRole->id]);
            }

            return;
        }

        $seeded = Employee::query()
            ->where(function ($q) {
                $q->where('role', 'like', '%boss%')
                    ->orWhereHas('roles', fn ($r) => $r->where('slug', Role::BOSS));
            })
            ->first();

        if ($seeded) {
            $seeded->update(['phone' => $phone]);

            return;
        }

        Employee::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace?->id,
            'name' => $this->sender_name ?: 'Boss',
            'email' => 'sim-boss-'.Str::random(6).'@thespace.local',
            'phone' => $phone,
            'role' => 'Executive Boss',
            'is_available' => true,
        ])->roles()->sync($bossRole ? [$bossRole->id] : []);
    }

    /**
     * Dispatch through the real WhatsApp inbound pipeline (ProcessIncomingMessage job).
     */
    public function sendRealWorkflow(): void
    {
        $this->validate([
            'customer_phone' => 'required|string',
            'sender_name' => 'required|string',
            'customer_message' => 'nullable|string',
            'attachments.*' => 'nullable|file',
        ]);

        $this->prepareCustomerForWorkflow();

        $phone = $this->customer_phone;
        $senderName = $this->sender_name;
        $messageText = $this->customer_message ?: '';
        $simControls = $this->simControls();
        $dispatched = 0;

        if (! empty($this->attachments)) {
            $caption = $messageText ?: 'Media Received';

            foreach ($this->attachments as $file) {
                if (! $file) {
                    continue;
                }

                $storedPath = $file->store('simulated-attachments', 'local');
                if (! $storedPath) {
                    continue;
                }

                $payload = WhatsAppInboundPayload::media(
                    fromPhone: $phone,
                    profileName: $senderName,
                    mimeType: $file->getMimeType() ?: 'application/octet-stream',
                    caption: $caption,
                    filename: $file->getClientOriginalName(),
                    mediaId: 'livewire_media_'.(string) Str::uuid(),
                    simulatedPath: $storedPath,
                    simControls: $simControls,
                );

                ProcessIncomingMessage::dispatch($payload, 'whatsapp');
                $dispatched++;
            }

            if ($dispatched === 0) {
                session()->flash('error', 'Could not store attachments.');

                return;
            }
        } else {
            if ($messageText === '') {
                session()->flash('error', 'Provide a message or attach files.');

                return;
            }

            $payload = WhatsAppInboundPayload::text(
                $phone,
                $senderName,
                $messageText,
                null,
                $simControls,
            );
            ProcessIncomingMessage::dispatch($payload, 'whatsapp');
            $dispatched = 1;
        }

        $this->last_dispatched_phone = $phone;
        $this->refreshWorkflowResults();

        $hint = $this->instant_ai
            ? 'Instant AI cooldown is on — run `php artisan queue:work` if jobs are queued.'
            : 'Check the queue worker; default customer cooldown may delay AI.';

        session()->flash('success', "Dispatched {$dispatched} message(s) through the real pipeline. {$hint}");
    }

    public function refreshWorkflowResults(): void
    {
        $phone = $this->last_dispatched_phone ?: $this->customer_phone;
        if ($phone === '') {
            $this->workflowResults = null;

            return;
        }

        $customer = Customer::query()
            ->where('phone', $phone)
            ->orWhere('whatsapp_id', $phone)
            ->first();

        $conversation = $customer
            ? Conversation::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'whatsapp')
                ->latest('id')
                ->first()
            : null;

        $session = $conversation
            ? ConversationSession::query()
                ->where('conversation_id', $conversation->id)
                ->latest('id')
                ->first()
            : null;

        $tasks = [];
        if ($session) {
            $taskIds = $session->taskIdList();
            if ($taskIds !== []) {
                $tasks = Task::query()
                    ->whereIn('id', $taskIds)
                    ->with('currentState')
                    ->get(['id', 'uuid', 'title', 'completed_at', 'metadata', 'current_state_id', 'workflow_id'])
                    ->map(fn (Task $task) => [
                        'id' => $task->id,
                        'uuid' => $task->uuid,
                        'title' => $task->title,
                        'status' => $task->status instanceof TaskStatus
                            ? $task->status->value
                            : (string) $task->status,
                        'completed' => $task->isCompleted(),
                        'source' => data_get($task->metadata, 'source'),
                        'url' => route('workspace.task-detail', ['record' => $task->uuid ?? $task->id]),
                    ])
                    ->all();
            }
        }

        $outbound = [];
        if ($conversation) {
            $outbound = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', MessageDirection::Outbound->value)
                ->latest('id')
                ->limit(5)
                ->get(['id', 'body', 'subject', 'created_at', 'metadata'])
                ->map(fn (Message $msg) => [
                    'id' => $msg->id,
                    'body' => Str::limit((string) $msg->body, 160),
                    'subject' => $msg->subject,
                    'created_at' => optional($msg->created_at)?->toDateTimeString(),
                    'kind' => data_get($msg->metadata, 'kind')
                        ?? data_get($msg->metadata, 'outbound_kind')
                        ?? data_get($msg->metadata, 'trigger'),
                ])
                ->all();
        }

        $conversationUrl = $conversation
            ? route('conversation-center', [
                'conversation' => $conversation->id,
                'session' => $session?->id,
            ])
            : null;

        $this->workflowResults = [
            'phone' => $phone,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'verified' => (bool) data_get($customer->metadata, 'project_verified'),
                'needs_verification' => (bool) data_get($customer->metadata, 'needs_project_verification')
                    || str_contains((string) $customer->name, '(Unknown)'),
                'boss_notes' => data_get($customer->metadata, 'boss_notes'),
            ] : null,
            'conversation_id' => $conversation?->id,
            'session' => $session ? [
                'id' => $session->id,
                'uuid' => $session->uuid,
                'status' => $session->status instanceof ConversationSessionStatus
                    ? $session->status->value
                    : (string) $session->status,
                'title' => $session->title,
                'summary' => $session->summary,
                'needs_review' => (bool) $session->needs_review,
                'project_id' => $session->project_id,
                'task_count' => count($session->taskIdList()),
            ] : null,
            'tasks' => $tasks,
            'outbound' => $outbound,
            'conversation_url' => $conversationUrl,
            'refreshed_at' => now()->toDateTimeString(),
        ];
    }

    public function markLatestSessionTasksDone(): void
    {
        $this->refreshWorkflowResults();
        $sessionId = data_get($this->workflowResults, 'session.id');
        if (! $sessionId) {
            session()->flash('error', 'No conversation session found for this phone. Dispatch a known/boss message first.');

            return;
        }

        $session = ConversationSession::find($sessionId);
        if (! $session) {
            session()->flash('error', 'Session not found.');

            return;
        }

        $taskIds = $session->taskIdList();
        if ($taskIds === []) {
            session()->flash('error', 'Session has no tasks yet. Wait for AI to create tasks, then try again.');

            return;
        }

        $done = 0;
        foreach (Task::query()->whereIn('id', $taskIds)->with('currentState')->get() as $task) {
            if ($task->isCompleted()) {
                continue;
            }

            // `status` is a virtual attribute (workflow state) — must assign, not mass-fill.
            $task->status = TaskStatus::Done;
            $task->completed_at = now();
            $task->save();
            ConversationSession::syncDoneForTask($task->fresh(['currentState']));
            $done++;
        }

        $this->refreshWorkflowResults();
        session()->flash('success', $done > 0
            ? "Marked {$done} task(s) Done. Session should move to Done and may send a completion auto-reply."
            : 'All linked tasks were already Done.');
    }

    public function simulateE2E(): void
    {
        $this->validate([
            'customer_phone' => 'required|string',
            'sender_name' => 'required|string',
            'customer_message' => 'required|string',
            'attachments.*' => 'nullable|file',
        ]);

        $phone = $this->customer_phone;
        $senderName = $this->sender_name;
        $messageText = $this->customer_message;
        $bossNotes = $this->boss_notes;

        $results = [
            'steps' => [],
            'tasks_created' => [],
            'ai_output' => null,
        ];

        $customerService = app(CustomerService::class);
        $customer = $customerService->findOrCreateByPhone($phone, [
            'name' => $senderName,
            'whatsapp_id' => $phone,
        ]);
        $results['steps'][] = [
            'stage' => 'Customer',
            'status' => 'success',
            'detail' => "Customer: {$customer->name} (ID: {$customer->id})",
        ];

        $employee = Employee::where('phone', $phone)->first();
        $isBoss = $employee && $employee->isBoss();
        $results['is_boss'] = $isBoss;

        $conversationService = app(\Modules\Communication\Services\ConversationService::class);
        $conversation = $conversationService->findOrCreateActive($customer, 'whatsapp');
        $results['steps'][] = [
            'stage' => 'Conversation',
            'status' => 'success',
            'detail' => "Conversation ID: {$conversation->id} (Channel: whatsapp)",
        ];

        $message = $conversationService->addMessage($conversation, [
            'channel' => \Modules\Communication\Enums\MessageChannel::WhatsApp->value,
            'direction' => MessageDirection::Inbound->value,
            'sender_identifier' => $phone,
            'sender_name' => $senderName,
            'subject' => $isBoss ? 'Boss Command' : 'WhatsApp Message',
            'body' => $messageText,
            'raw_payload' => ['simulated' => true],
            'customer_id' => $customer->id,
            'status' => \Modules\Communication\Enums\MessageStatus::Received->value,
            'processing_status' => [
                'media_downloaded' => true,
                'ai_analyzed' => false,
                'issue_linked' => false,
                'task_created' => false,
            ],
        ]);
        $results['steps'][] = [
            'stage' => 'Message Saved',
            'status' => 'success',
            'detail' => "Message ID: {$message->id}",
        ];

        $createdAttachments = [];
        if (! empty($this->attachments)) {
            foreach ($this->attachments as $file) {
                $filePath = $file->store('simulated-attachments', 'public');
                $mimeType = $file->getMimeType();
                $typeEnum = \Modules\Attachments\Enums\AttachmentType::fromMimeType($mimeType);
                $att = $message->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $filePath,
                    'disk' => 'public',
                    'size_bytes' => $file->getSize(),
                    'mime_type' => $mimeType,
                    'type' => $typeEnum?->value ?? 'document',
                ]);
                $createdAttachments[] = $att;
            }

            $results['steps'][] = [
                'stage' => 'Attachments Simulated',
                'status' => 'success',
                'detail' => count($createdAttachments)." file(s) attached to Message #{$message->id}",
            ];
        }

        $prompt = \App\Models\AiPrompt::with('schema')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if (! $prompt || ! $prompt->schema) {
            $results['steps'][] = ['stage' => 'AI Analysis', 'status' => 'failed', 'detail' => 'No active AI Prompt/Schema found.'];
            $this->e2eResults = $results;

            return;
        }

        $providerKey = $this->ai_provider ?: 'gemini';
        $model = \App\Models\AiModel::where('provider', $providerKey)->where('is_active', true)->first()
            ?: \App\Models\AiModel::where('is_active', true)->first();

        if (! $model) {
            $results['steps'][] = ['stage' => 'AI Analysis', 'status' => 'failed', 'detail' => 'No active AI Model found.'];
            $this->e2eResults = $results;

            return;
        }

        $activeProjects = Project::where('status', 'active')->pluck('name')->toArray();
        $employees = Employee::where('is_available', true)->pluck('name')->toArray();
        $projectHints = empty($activeProjects) ? 'None' : implode(', ', $activeProjects);
        $employeeHints = empty($employees) ? 'None' : implode(', ', $employees);

        $aiMessage = $prompt->user_prompt_template ?? '{{message}}';
        $aiMessage = str_replace('{{message}}', $messageText, $aiMessage);
        $aiMessage = str_replace('{{boss_notes}}', $bossNotes, $aiMessage);
        $aiMessage = str_replace('{{projects}}', $projectHints, $aiMessage);
        $aiMessage = str_replace('{{employees}}', $employeeHints, $aiMessage);

        try {
            $manager = new \App\Services\AI\AIManager;
            $aiRequestLog = $manager->execute($aiMessage, $model, $prompt, $prompt->schema);

            if ($aiRequestLog->validation_status !== 'passed') {
                $results['steps'][] = ['stage' => 'AI Analysis', 'status' => 'failed', 'detail' => 'Validation failed: '.$aiRequestLog->error_message];
                $results['ai_output'] = $aiRequestLog->parsed_json;
                $this->e2eResults = $results;

                return;
            }

            $aiOutput = $aiRequestLog->parsed_json;
            $results['ai_output'] = $aiOutput;
            $results['steps'][] = [
                'stage' => 'AI Analysis',
                'status' => 'success',
                'detail' => 'Intent: '.($aiOutput['intent'] ?? 'unknown').' | Actionable: '.(($aiOutput['is_actionable'] ?? false) ? 'Yes' : 'No').' | Tasks: '.count($aiOutput['tasks'] ?? []),
            ];

            $metadata = $conversation->metadata ?? [];
            $metadata['ai_analyses'] = [['provider' => $model->provider, 'timestamp' => now()->toIso8601String(), 'result' => $aiOutput]];
            $conversation->update(['metadata' => $metadata]);

            $status = $message->processing_status ?? [];
            $status['ai_analyzed'] = true;
            $message->update(['processing_status' => $status]);
        } catch (\Exception $e) {
            $results['steps'][] = ['stage' => 'AI Analysis', 'status' => 'failed', 'detail' => $e->getMessage()];
            $this->e2eResults = $results;

            return;
        }

        if (! ($aiOutput['is_actionable'] ?? false)) {
            $results['steps'][] = [
                'stage' => 'Task Creation',
                'status' => 'skipped',
                'detail' => 'Message not actionable. No tasks created.',
            ];
            $this->e2eResults = $results;
            session()->flash('info', 'E2E Complete — Not Actionable');

            return;
        }

        $workflowManager = app(\Modules\Workflows\Services\WorkflowManager::class);
        $initialState = $workflowManager->getDefaultState('task');
        $projectName = $aiOutput['project'] ?? '';
        $projectModel = ! empty($projectName) ? Project::where('name', 'LIKE', "%{$projectName}%")->first() : null;

        foreach ($aiOutput['tasks'] ?? [] as $taskData) {
            $task = Task::create([
                'project_id' => $projectModel?->id,
                'type' => 'task',
                'title' => $taskData['title'] ?? 'New Task',
                'summary' => $aiOutput['summary'] ?? '',
                'description' => $taskData['description'] ?? '',
                'workflow_id' => $initialState?->workflow_id,
                'current_state_id' => $initialState?->id,
                'priority' => $taskData['priority'] ?? 'medium',
                'sync_status' => 'queued',
                'metadata' => [
                    'source' => 'e2e_simulation',
                    'conversation_id' => $conversation->id,
                    'ai_confidence' => $aiOutput['confidence'] ?? 0,
                ],
            ]);

            $assignedEmployee = \Modules\Tasks\Services\TaskAutoAssignmentService::assignTask($task, $taskData['assigned_to'] ?? '');

            if (! empty($createdAttachments)) {
                foreach ($createdAttachments as $att) {
                    $task->attachments()->create([
                        'original_name' => $att->original_name ?? $att->file_name,
                        'stored_path' => $att->stored_path ?? $att->file_path,
                        'disk' => $att->disk ?? 'public',
                        'size_bytes' => $att->size_bytes ?? $att->file_size,
                        'mime_type' => $att->mime_type,
                        'type' => $att->type,
                        'ai_transcript' => $att->ai_transcript,
                    ]);
                }
            }

            $priorityValue = $task->priority instanceof \BackedEnum ? $task->priority->value : (string) ($task->priority ?? 'medium');

            $results['tasks_created'][] = [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $priorityValue,
                'assigned_to' => $assignedEmployee?->name ?? 'Unassigned',
                'attachments_count' => count($createdAttachments),
            ];
        }

        $results['steps'][] = [
            'stage' => 'Task Creation',
            'status' => 'success',
            'detail' => count($results['tasks_created']).' task(s) created.',
        ];

        $this->e2eResults = $results;
        session()->flash('success', 'E2E Simulation Complete!');
    }

    public function runPipeline(): void
    {
        $this->validate([
            'customer_phone' => 'required|string',
            'sender_name' => 'required|string',
            'customer_message' => 'required|string',
        ]);

        $payload = [
            'workspace' => $this->workspace,
            'project' => $this->project,
            'customer_phone' => $this->customer_phone,
            'sender_name' => $this->sender_name,
            'boss_notes' => $this->boss_notes,
            'customer_message' => $this->customer_message,
            'ai_provider' => $this->ai_provider ?: 'mock',
            'chaos_mode' => $this->chaos_mode,
            'correlation_id' => (string) Str::uuid(),
        ];

        if ($this->certification_test_id) {
            $payload['certification_test_id'] = $this->certification_test_id;
        }

        $pipeline = new \App\Services\Pipeline\MessagePipeline($payload, true);
        $this->pipelineLog = $pipeline->execute();

        session()->flash('success', 'Pipeline Executed');
    }

    public function runWithoutAi(): void
    {
        $this->validate([
            'customer_phone' => 'required|string',
            'sender_name' => 'required|string',
            'customer_message' => 'required|string',
        ]);

        $payload = [
            'workspace' => $this->workspace,
            'project' => $this->project,
            'customer_phone' => $this->customer_phone,
            'sender_name' => $this->sender_name,
            'boss_notes' => $this->boss_notes,
            'customer_message' => $this->customer_message,
            'ai_provider' => $this->ai_provider ?: 'mock',
            'chaos_mode' => $this->chaos_mode,
            'correlation_id' => (string) Str::uuid(),
        ];

        if ($this->certification_test_id) {
            $payload['certification_test_id'] = $this->certification_test_id;
        }

        $pipeline = new \App\Services\Pipeline\MessagePipeline($payload, false);
        $this->pipelineLog = $pipeline->execute();

        session()->flash('success', 'Pipeline Executed (No AI)');
    }

    /**
     * @deprecated Use sendRealWorkflow() instead.
     */
    public function sendInboundRealJobs(): void
    {
        $this->sendRealWorkflow();
    }

    public function render()
    {
        return view('livewire.message-simulator.index', [
            'certificationTests' => \App\Models\CertificationTest::all(),
            'workspaces' => Workspace::all(),
            'projects' => Project::all(),
            'demoChecklist' => [
                ['label' => 'Unknown number → project-code prompt', 'scenario' => 'unknown'],
                ['label' => 'Send project code (after unknown)', 'scenario' => 'project_code'],
                ['label' => 'Known verified customer → AI tasks', 'scenario' => 'known'],
                ['label' => 'Boss WhatsApp command', 'scenario' => 'boss'],
                ['label' => 'Task created (actionable request)', 'scenario' => 'task_created'],
                ['label' => 'AI error → Needs Review', 'scenario' => 'ai_error'],
                ['label' => 'Boss notes + project targeting', 'scenario' => 'boss_notes_project'],
                ['label' => 'All tasks done (use helper after tasks exist)', 'scenario' => null],
            ],
        ]);
    }
}
