<?php

namespace App\Livewire\ConversationCenter;

use App\Helpers\InboxCounts;
use App\Helpers\RailBadges;
use App\Livewire\Concerns\AuthorizesActions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Services\ConversationService;
use Modules\Communication\Services\WhatsAppCloudService;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Services\WorkflowManager;

class Index extends Component
{
    use AuthorizesActions;

    public ?int $selectedConversationId = null;

    public ?int $selectedSessionId = null;

    public ?int $selectedCustomerId = null;

    public string $replyMessage = '';

    public string $filterTab = 'all';

    public string $searchQuery = '';

    public bool $showStartModal = false;

    public bool $showApproveModal = false;

    public ?string $queuedAiAt = null;

    public ?string $copilotCooldownEndsAt = null;

    public mixed $startCustomerId = null;

    public string $startPlatform = 'whatsapp';

    public string $startContactIdentifier = '';

    public string $approveTitle = '';

    public mixed $approveProjectId = null;

    public string $approvePriority = 'high';

    public mixed $approveAssigneeId = null;

    public string $approveDescription = '';

    /**
     * Message ids in the open session that staff picked to turn into a task.
     *
     * @var list<int>
     */
    public array $selectedMessageIds = [];

    public bool $creatingFromSelection = false;

    public bool $showTaskReviewModal = false;

    public ?int $reviewingTaskId = null;

    public string $reviewingTaskTitle = '';

    public string $reviewNote = '';

    protected function queryString(): array
    {
        return [
            'filterTab' => ['as' => 'tab', 'except' => 'all'],
            'selectedCustomerId' => ['as' => 'customer', 'except' => null],
            'selectedConversationId' => ['as' => 'conversation', 'except' => null],
            'selectedSessionId' => ['as' => 'session', 'except' => null],
            'searchQuery' => ['as' => 'q', 'except' => ''],
        ];
    }

    public function mount(): void
    {
        if (request()->filled('tab') && in_array(request('tab'), ['all', 'unread', 'closed', 'done', 'review'], true)) {
            $this->filterTab = request('tab') === 'closed' ? 'done' : request('tab');
        }

        if (request()->filled('customer')) {
            $this->selectedCustomerId = (int) request('customer');
        }

        if (request()->filled('conversation')) {
            $this->selectedConversationId = (int) request('conversation');
        }

        if (request()->filled('session')) {
            $this->selectedSessionId = (int) request('session');
        }

        if (request()->filled('q')) {
            $this->searchQuery = (string) request('q');
        }

        if (request()->boolean('create')) {
            $this->openStartModal();
        }

        $this->markSelectedRead();
    }

    public function selectConversation(int $id): void
    {
        $this->selectedConversationId = $id;
        $this->selectedSessionId = ConversationSession::query()
            ->where('conversation_id', $id)
            ->latest('last_message_at')
            ->latest('id')
            ->value('id');
        $customerId = Conversation::query()->whereKey($id)->value('customer_id');
        $this->selectedCustomerId = $customerId ? (int) $customerId : null;
        $this->queuedAiAt = null;
        $this->copilotCooldownEndsAt = null;
        $this->markSelectedRead();
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->selectedConversationId = null;
        $this->selectedSessionId = null;
        $this->clearMessageSelection();
        $this->queuedAiAt = null;
        $this->copilotCooldownEndsAt = null;
    }

    public function clearSessionSelection(): void
    {
        $this->selectedSessionId = null;
        $this->selectedConversationId = null;
        $this->clearMessageSelection();
        $this->queuedAiAt = null;
        $this->copilotCooldownEndsAt = null;
    }

    public function clearCustomerSelection(): void
    {
        $this->selectedCustomerId = null;
        $this->selectedConversationId = null;
        $this->selectedSessionId = null;
        $this->clearMessageSelection();
        $this->queuedAiAt = null;
        $this->copilotCooldownEndsAt = null;
    }

    public function refreshOpenThread(): void
    {
        $this->markSelectedRead();
    }

    public function setFilter(string $tab): void
    {
        if ($tab === 'closed') {
            $tab = 'done';
        }
        if (! in_array($tab, ['all', 'unread', 'done', 'review'], true)) {
            $tab = 'all';
        }
        $this->filterTab = $tab;
        $this->selectedCustomerId = null;
        $this->selectedConversationId = null;
        $this->selectedSessionId = null;
        $this->clearMessageSelection();
    }

    public function updatedSelectedSessionId(): void
    {
        $this->clearMessageSelection();
    }

    public function toggleMessageSelection(int $messageId): void
    {
        $session = $this->selectedSession();
        if (! $session) {
            return;
        }

        $belongs = $session->messages()->whereKey($messageId)->exists();
        if (! $belongs) {
            return;
        }

        $ids = array_map('intval', $this->selectedMessageIds);
        if (in_array($messageId, $ids, true)) {
            $this->selectedMessageIds = array_values(array_filter($ids, fn (int $id) => $id !== $messageId));

            return;
        }

        $this->selectedMessageIds = array_values(array_unique([...$ids, $messageId]));
    }

    public function clearMessageSelection(): void
    {
        $this->selectedMessageIds = [];
        $this->creatingFromSelection = false;
    }

    public function openStartModal(): void
    {
        $this->startCustomerId = null;
        $this->startPlatform = 'whatsapp';
        $this->startContactIdentifier = '';
        $this->showStartModal = true;
    }

    public function startConversation(): void
    {
        $this->validate([
            'startCustomerId' => 'required|exists:customers,id',
            'startPlatform' => 'required|string|max:50',
            'startContactIdentifier' => 'required|string|max:100',
        ]);

        $customer = Customer::find($this->startCustomerId);
        if ($customer && empty($customer->phone)) {
            $customer->update(['phone' => $this->startContactIdentifier]);
        }

        $conversation = Conversation::create([
            'customer_id' => $this->startCustomerId,
            'channel' => $this->startPlatform,
            'status' => 'active',
            'last_message_at' => now(),
            'metadata' => [
                'contact_identifier' => $this->startContactIdentifier,
            ],
        ]);

        $session = ConversationSession::create([
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Collecting,
            'started_at' => now(),
            'last_message_at' => now(),
            'task_ids' => [],
        ]);

        $this->selectedConversationId = $conversation->id;
        $this->selectedSessionId = $session->id;
        $this->markSelectedRead();
        $this->showStartModal = false;
        session()->flash('success', 'Conversation started');
    }

    public function openApproveModal(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $conversation = Conversation::with(['customer', 'messages'])->find($this->selectedConversationId);
        $session = $this->selectedSession();
        if (! $conversation || ! $session) {
            return;
        }

        $this->creatingFromSelection = false;
        $copilot = $this->copilotState($session, $conversation);
        if (! empty($copilot['auto_created'])) {
            session()->flash('error', 'This session already created tasks automatically.');

            return;
        }

        $latestMessage = $session->messages()
            ->get()
            ->filter(fn ($m) => $this->isInbound($m))
            ->sortBy('created_at')
            ->last();
        $result = $copilot['result'] ?? [];
        $tasks = $result['tasks'] ?? [];
        $firstTask = is_array($tasks) && ! empty($tasks) ? $tasks[0] : [];

        $title = $firstTask['title'] ?? $result['title'] ?? $result['summary'] ?? 'Customer Issue: '.($conversation->customer?->name ?? 'WhatsApp Request');
        if (str_contains((string) $title, 'Manual Triage Needed') || empty($title)) {
            $title = 'Fix issue reported by '.($conversation->customer?->name ?? 'Customer');
        }

        $priority = $firstTask['priority'] ?? $result['priority'] ?? 'medium';
        if (! in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }

        $this->approveTitle = $title;
        $this->approveDescription = $firstTask['description'] ?? $result['description'] ?? $result['summary'] ?? $latestMessage?->body ?? 'Generated from WhatsApp conversation thread.';
        $this->approvePriority = $priority;
        $this->fillApproveProject($conversation->customer_id, $copilot['project'] ?? null);
        $this->approveAssigneeId = null;
        $this->showApproveModal = true;
    }

    public function openApproveModalFromSelection(): void
    {
        $conversation = Conversation::with('customer')->find($this->selectedConversationId);
        $session = $this->selectedSession();
        $messages = $this->selectedSessionMessages();
        if (! $conversation || ! $session || $messages->isEmpty()) {
            session()->flash('error', 'Select one or more messages first.');

            return;
        }

        $this->creatingFromSelection = true;
        $this->approveTitle = $this->titleFromMessages($messages, $conversation->customer?->name);
        $this->approveDescription = $this->descriptionFromMessages($messages);
        $this->approvePriority = 'medium';
        $this->fillApproveProject($conversation->customer_id);
        $this->approveAssigneeId = null;
        $this->showApproveModal = true;
    }

    public function approveAndCreateTask(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $this->validate([
            'approveTitle' => 'required|string|max:255',
            'approveProjectId' => 'nullable|exists:projects,id',
            'approvePriority' => 'required|in:low,medium,high,urgent',
            'approveAssigneeId' => 'nullable|exists:employees,id',
            'approveDescription' => 'nullable|string',
        ]);

        $conversation = Conversation::find($this->selectedConversationId);
        $session = $this->selectedSession();
        if (! $conversation || ! $session) {
            return;
        }

        $workflowManager = app(WorkflowManager::class);
        $initialState = $workflowManager->getDefaultState('task');

        $task = Task::create([
            'project_id' => $this->approveProjectId,
            'type' => 'task',
            'title' => $this->approveTitle,
            'summary' => Str::limit($this->approveDescription ?? '', 100),
            'description' => $this->approveDescription ?: null,
            'workflow_id' => $initialState?->workflow_id,
            'current_state_id' => $initialState?->id,
            'priority' => $this->approvePriority,
            'sync_status' => 'queued',
            'created_by' => auth()->user()?->id,
            'metadata' => [
                'conversation_id' => $conversation->id,
                'conversation_session_id' => $session->id,
                'customer_id' => $conversation->customer_id,
                'source' => $this->creatingFromSelection ? 'conversation_messages' : 'conversation_copilot',
                'source_message_ids' => $this->creatingFromSelection
                    ? array_values(array_unique(array_map('intval', $this->selectedMessageIds)))
                    : [],
            ],
        ]);

        if ($this->creatingFromSelection) {
            $this->linkSelectedMessageAttachments($task, $session);
        }

        if ($this->approveAssigneeId) {
            $assignerEmployeeId = auth()->user()?->resolveEmployee()?->id;
            $task->assignments()->create([
                'employee_id' => $this->approveAssigneeId,
                'status' => 'accepted',
                'role' => 'assignee',
                'assigned_by' => $assignerEmployeeId,
                'assigned_at' => now(),
            ]);
        }

        $taskIds = $session->taskIdList();
        $taskIds[] = $task->id;
        $session->update([
            'task_ids' => array_values(array_unique($taskIds)),
            'auto_created' => false,
            'status' => ConversationSessionStatus::Open,
        ]);
        $session->refreshStatusFromTasks();

        $this->showApproveModal = false;
        $this->clearMessageSelection();
        session()->flash('success', "Task '{$task->title}' has been successfully created.");
    }

    public function approveSessionTask(int $taskId): void
    {
        $task = Task::find($taskId);
        $actor = auth()->user()?->resolveEmployee();
        if (! $task || ! $actor || ! $this->isManager()) {
            return;
        }

        $approved = app(NativeTaskService::class)->approveTask($task, $actor);
        session()->flash(
            $approved ? 'success' : 'error',
            $approved ? 'Task approved and marked done.' : 'Only a manager or assigned reviewer can approve this task.'
        );
    }

    public function openSessionReviewModal(int $taskId): void
    {
        $task = Task::find($taskId);
        if (! $task || ! $this->isManager()) {
            return;
        }

        $this->reviewingTaskId = $task->id;
        $this->reviewingTaskTitle = $task->title;
        $this->reviewNote = '';
        $this->showTaskReviewModal = true;
    }

    public function closeSessionReviewModal(): void
    {
        $this->showTaskReviewModal = false;
        $this->reviewingTaskId = null;
        $this->reviewingTaskTitle = '';
        $this->reviewNote = '';
    }

    public function submitSessionReview(): void
    {
        $task = Task::find($this->reviewingTaskId);
        $actor = auth()->user()?->resolveEmployee();
        if (! $task || ! $actor || ! $this->isManager()) {
            $this->closeSessionReviewModal();

            return;
        }

        $requested = app(NativeTaskService::class)->requestChanges($task, $actor, $this->reviewNote ?: null);
        $this->closeSessionReviewModal();
        session()->flash(
            $requested ? 'success' : 'error',
            $requested ? 'Changes requested. The task was sent back to In Progress.' : 'Only a manager or assigned reviewer can request changes.'
        );
    }

    public function deleteConversation($id): void
    {
        $targetId = $id ?? $this->selectedConversationId;
        if (! $targetId) {
            return;
        }

        $conversation = Conversation::find($targetId);
        if ($conversation) {
            $conversation->messages()->delete();
            $conversation->issues()->delete();
            $conversation->sessions()->delete();
            $conversation->delete();

            if ($this->selectedConversationId === $targetId) {
                $this->selectedConversationId = null;
                $this->selectedSessionId = null;
            }

            session()->flash('success', 'Conversation Deleted');
        }
    }

    public function sendReply(): void
    {
        if (! $this->selectedConversationId || trim($this->replyMessage) === '') {
            return;
        }

        $conversation = Conversation::find($this->selectedConversationId);
        $session = $this->selectedSession();
        if (! $conversation || ! $session || ! $this->isLatestSession($conversation, $session)) {
            return;
        }

        $message = app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'body' => $this->replyMessage,
            'status' => MessageStatus::Processing,
            'sender_identifier' => auth()->user()?->email ?? 'agent@thespace.app',
            'sender_name' => auth()->user()?->name ?? 'Agent',
            'recipient_identifier' => $conversation->customer?->whatsapp_id ?? $conversation->customer?->phone,
            'customer_id' => $conversation->customer_id,
        ]);

        SendOutboundMessage::dispatch($message);

        $this->markSelectedRead();

        $this->replyMessage = '';
        session()->flash('success', 'Message queued for WhatsApp.');
    }

    public function reanalyzeWithAi(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $conversation = Conversation::find($this->selectedConversationId);
        $session = $this->selectedSession();
        if (! $conversation || ! $session) {
            return;
        }

        $this->queuedAiAt = now()->toIso8601String();

        $workspace = Workspace::first();
        $cooldownMinutes = (int) ($workspace?->settings['customer_cooldown_minutes'] ?? 5);
        $this->copilotCooldownEndsAt = now()->addMinutes($cooldownMinutes)->toIso8601String();

        $session->load('messages');
        foreach ($session->messages as $message) {
            if (! $this->isInbound($message)) {
                continue;
            }
            $status = $message->processing_status ?? [];
            $status['ai_analyzed'] = false;
            $message->update(['processing_status' => $status]);
        }
        $session->update([
            'status' => ConversationSessionStatus::Collecting,
            'ended_at' => null,
        ]);
        ProcessBufferedConversation::schedule($conversation, false, null, $session->id);
        session()->flash('success', 'AI analysis queued after cooldown.');
    }

    public function bypassCooldown(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $conversation = Conversation::find($this->selectedConversationId);
        $session = $this->selectedSession();
        if (! $conversation || ! $session) {
            return;
        }

        $this->copilotCooldownEndsAt = null;
        $this->queuedAiAt = now()->toIso8601String();

        $session->load('messages');
        foreach ($session->messages as $message) {
            if (! $this->isInbound($message)) {
                continue;
            }
            $status = $message->processing_status ?? [];
            $status['ai_analyzed'] = false;
            $message->update(['processing_status' => $status]);
        }
        $session->update([
            'status' => ConversationSessionStatus::Collecting,
            'ended_at' => null,
        ]);

        ProcessBufferedConversation::schedule($conversation, false, 0, $session->id);
        session()->flash('success', 'Cooldown bypassed — AI analysis running now.');
    }

    public function downloadAttachment(string $uuid)
    {
        $attachment = Attachment::where('uuid', $uuid)->first();
        if (! $attachment || ! $attachment->stored_path) {
            session()->flash('error', 'Attachment is not available yet.');

            return;
        }

        // Don't return a binary response from a Livewire action; Livewire will
        // try to JSON-encode it and crash (see JsonResponse "Type is not supported").
        return $this->redirect(route('attachments.download', ['uuid' => $uuid]));
    }

    public function render()
    {
        $sessions = ConversationSession::query()
            ->with(['conversation.customer', 'latestMessage'])
            ->whereHas('conversation')
            ->tab($this->filterTab)
            ->searchCustomers($this->searchQuery)
            ->latest('last_message_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $selectedSession = $this->resolveSelectedSession($sessions);
        $selectedConversation = $selectedSession?->conversation;

        if ($selectedSession && ! $selectedConversation) {
            $selectedSession = null;
        }

        if ($selectedSession && $selectedConversation) {
            $this->selectedSessionId = $selectedSession->id;
            $this->selectedConversationId = $selectedConversation->id;
            $selectedSession->refresh();
            $selectedSession->load([
                'messages' => fn ($q) => $q->orderBy('created_at'),
                'messages.attachments',
                'conversation.customer',
                'conversation.issues',
            ]);
            $selectedConversation = $selectedSession->conversation;
        }

        if ($selectedSession && $selectedSession->taskIdList() !== []) {
            $selectedSession->refreshStatusFromTasks();
        }

        $copilot = $this->copilotState($selectedSession, $selectedConversation);
        if ($this->queuedAiAt && in_array($copilot['state'], ['ready', 'failed', 'needs_review'], true)) {
            $this->queuedAiAt = null;
            $this->copilotCooldownEndsAt = null;
        }

        if ($selectedSession && $selectedConversation?->customer_id) {
            $this->selectedCustomerId = (int) $selectedConversation->customer_id;
        }

        $sessionGroups = $this->sessionGroupsForBrowse($sessions);
        $selectedGroup = null;
        if (! $selectedSession && $this->selectedCustomerId) {
            $selectedGroup = collect($sessionGroups)->first(
                fn (array $group) => (int) ($group['id'] ?? 0) === (int) $this->selectedCustomerId
            );
            if (! $selectedGroup) {
                $this->selectedCustomerId = null;
            }
        }

        $customer = $selectedConversation?->customer
            ?? ($this->selectedCustomerId ? Customer::find($this->selectedCustomerId) : null);
        $sessionTasks = ! empty($copilot['task_ids'])
            ? Task::with(['project', 'currentState', 'reviewers'])->whereIn('id', $copilot['task_ids'])->get()
            : collect();
        $customerProjects = $customer
            ? app(ProjectService::class)->projectsForCustomer($customer->id)
            : collect();

        $workspace = Workspace::first();
        $cooldownTotalSeconds = max(1, (int) ($workspace?->settings['customer_cooldown_minutes'] ?? 5) * 60);

        return view('livewire.conversation-center.index', [
            'conversations' => $sessions->map->conversation->filter()->unique('id')->values(),
            'selected_conversation' => $selectedConversation,
            'selected_session' => $selectedSession,
            'session_groups' => $sessionGroups,
            'selected_group' => $selectedGroup,
            'browse_customer' => $customer && ! $selectedSession ? $customer : null,
            'can_reply' => $selectedConversation && $selectedSession
                ? $this->isLatestSession($selectedConversation, $selectedSession)
                : false,
            'filter_tab' => $this->filterTab,
            'customers' => Customer::pluck('name', 'id'),
            'projects' => Project::query()->orderBy('name')->pluck('name', 'id')->toArray(),
            'employees' => Employee::pluck('name', 'id'),
            'copilot' => $copilot,
            'session_tasks' => $sessionTasks,
            'isManager' => $this->isManager(),
            'customer_projects' => $customerProjects,
            'cooldownTotalSeconds' => $cooldownTotalSeconds,
        ]);
    }

    /**
     * Group sessions by customer for drill-down (open first, then done).
     *
     * @param  Collection<int, ConversationSession>  $sessions
     * @return list<array{
     *     key: string,
     *     id: int|null,
     *     name: string,
     *     phone: string|null,
     *     preview: string,
     *     time: string,
     *     unread_count: int,
     *     session_count: int,
     *     open: list<array{id: int, conversation_id: int, title: string, preview: string, time: string, unread: bool, done: bool, awaiting_review: bool, needs_review: bool, has_error: bool, collecting: bool}>,
     *     done: list<array{id: int, conversation_id: int, title: string, preview: string, time: string, unread: bool, done: bool, awaiting_review: bool, needs_review: bool, has_error: bool, collecting: bool}>
     * }>
     */
    protected function sessionGroupsForBrowse(Collection $sessions): array
    {
        $reviewTaskIds = ConversationSession::awaitingReviewTaskIds(
            $sessions->flatMap(fn (ConversationSession $session) => $session->taskIdList())->all()
        );

        $customers = [];
        foreach ($sessions as $session) {
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

            $row = [
                'id' => (int) $session->id,
                'conversation_id' => (int) $session->conversation_id,
                'title' => $session->displayTitle(),
                'preview' => Str::limit((string) ($session->latestMessage?->body ?? 'No messages yet'), 72),
                'time' => $session->last_message_at?->format('H:i') ?? $session->updated_at?->format('H:i') ?? '',
                'unread' => $session->isUnread(),
                'done' => $session->isDone(),
                'awaiting_review' => $reviewTaskIds !== [] && array_intersect($session->taskIdList(), $reviewTaskIds) !== [],
                'needs_review' => (bool) $session->needs_review,
                'has_error' => ! empty($session->metadata['error']),
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

        return array_values($customers);
    }

    /**
     * @param  Collection<int, ConversationSession>  $sessions
     */
    protected function resolveSelectedSession($sessions): ?ConversationSession
    {
        if ($this->selectedSessionId) {
            $fromList = $sessions->firstWhere('id', $this->selectedSessionId);
            if ($fromList) {
                return $fromList;
            }

            $loaded = ConversationSession::with(['conversation.customer'])->find($this->selectedSessionId);
            if ($loaded) {
                return $loaded;
            }
        }

        if ($this->selectedCustomerId && ! $this->selectedSessionId) {
            return null;
        }

        if ($this->selectedConversationId) {
            $fromList = $sessions->first(fn (ConversationSession $session) => (int) $session->conversation_id === (int) $this->selectedConversationId);
            if ($fromList) {
                return $fromList;
            }

            return ConversationSession::query()
                ->with(['conversation.customer'])
                ->where('conversation_id', $this->selectedConversationId)
                ->latest('last_message_at')
                ->latest('id')
                ->first();
        }

        return null;
    }

    protected function selectedSession(): ?ConversationSession
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        return ConversationSession::find($this->selectedSessionId);
    }

    /**
     * @return Collection<int, Message>
     */
    protected function selectedSessionMessages(): Collection
    {
        $session = $this->selectedSession();
        $ids = array_values(array_unique(array_map('intval', $this->selectedMessageIds)));
        if (! $session || $ids === []) {
            return collect();
        }

        return $session->messages()
            ->with('attachments')
            ->whereIn('id', $ids)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    protected function titleFromMessages(Collection $messages, ?string $customerName): string
    {
        foreach ($messages as $message) {
            $body = trim((string) $message->body);
            if ($body !== '') {
                return Str::limit($body, 80);
            }

            foreach ($message->attachments as $attachment) {
                $name = trim((string) ($attachment->original_name ?? ''));
                if ($name !== '') {
                    return $name;
                }

                $transcript = trim((string) ($attachment->ai_transcript ?? ''));
                if ($transcript !== '') {
                    return Str::limit($transcript, 80);
                }
            }
        }

        return $customerName
            ? 'Task from selected messages · '.$customerName
            : 'Task from selected messages';
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    protected function descriptionFromMessages(Collection $messages): string
    {
        $blocks = [];
        foreach ($messages as $message) {
            $body = trim((string) $message->body);
            if ($body !== '') {
                $blocks[] = $body;
            }

            foreach ($message->attachments as $attachment) {
                $name = trim((string) ($attachment->original_name ?? 'attachment'));
                $transcript = trim((string) ($attachment->ai_transcript ?? ''));
                if ($transcript !== '') {
                    $blocks[] = '['.$name.' transcript] '.$transcript;
                } elseif ($name !== '') {
                    $blocks[] = '[attachment] '.$name;
                }
            }
        }

        return implode("\n\n", $blocks);
    }

    protected function fillApproveProject(?int $customerId, mixed $aiProject = null): void
    {
        $sessionProjectId = $this->selectedSession()?->project_id;
        $matched = $customerId
            ? app(ProjectService::class)->matchForCustomer($customerId, $aiProject, $sessionProjectId)
            : null;
        $customerProjects = $customerId
            ? app(ProjectService::class)->projectsForCustomer($customerId)
            : collect();

        $this->approveProjectId = $matched?->id
            ?? $sessionProjectId
            ?? ($customerProjects->count() === 1 ? $customerProjects->first()?->id : null);
    }

    protected function linkSelectedMessageAttachments(Task $task, ConversationSession $session): void
    {
        $messages = $this->selectedSessionMessages();
        if ($messages->isEmpty()) {
            $ids = array_values(array_unique(array_map('intval', $this->selectedMessageIds)));
            $messages = $session->messages()->with('attachments')->whereIn('id', $ids)->get();
        }

        foreach ($messages as $message) {
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

    protected function isLatestSession(Conversation $conversation, ConversationSession $session): bool
    {
        $latestId = $conversation->sessions()->latest('id')->value('id');

        return $latestId !== null && (int) $latestId === (int) $session->id;
    }

    /**
     * @return array{state: string, result: array, provider: ?string, model: ?string, timestamp: ?string, summary: ?string, title: ?string, urgency: ?string, project: ?string, employee: ?string, tags: array}
     */
    protected function copilotState(?ConversationSession $session, ?Conversation $conversation = null): array
    {
        $empty = [
            'state' => 'none',
            'result' => [],
            'provider' => null,
            'model' => null,
            'timestamp' => null,
            'summary' => null,
            'title' => null,
            'urgency' => null,
            'project' => null,
            'employee' => null,
            'tags' => [],
            'request_log_id' => null,
            'auto_created' => false,
            'needs_review' => false,
            'task_ids' => [],
        ];

        if (! $session) {
            return $empty;
        }

        $conversation ??= $session->conversation;
        $result = is_array($session->analysis) ? $session->analysis : [];
        $title = $session->title ?: ($result['title'] ?? ($result['tasks'][0]['title'] ?? null));
        $tags = $result['tags'] ?? [];
        $failed = (is_string($title) && str_contains($title, 'Manual Triage Needed'))
            || in_array('ai_failed', $tags, true)
            || ! empty($session->metadata['error']);

        $skipSummary = $conversation?->metadata['ai_summary'] ?? null;
        $skipped = is_string($skipSummary) && str_contains(strtolower($skipSummary), 'unknown number');

        $messages = $session->relationLoaded('messages') ? $session->messages : $session->messages()->get();
        $hasInboundUnanalyzed = $messages
            ->filter(fn ($m) => $this->isInbound($m))
            ->contains(fn ($m) => empty(($m->processing_status ?? [])['ai_analyzed']));

        $sessionStamp = $session->ended_at?->toIso8601String();
        $queuedPending = $this->queuedAiAt !== null
            && ($sessionStamp === null || $sessionStamp < $this->queuedAiAt);

        $state = 'none';
        if ($queuedPending || $session->isCollecting()) {
            $state = $messages->isNotEmpty() ? 'pending' : 'none';
        } elseif ($session->status === ConversationSessionStatus::AwaitingVerification) {
            $state = 'awaiting_verification';
        } elseif ($failed) {
            $state = 'failed';
        } elseif ($session->status === ConversationSessionStatus::NeedsReview && ! empty($result)) {
            $state = 'needs_review';
        } elseif (! empty($result)) {
            $state = 'ready';
        } elseif ($skipped) {
            $state = 'skipped';
        } elseif ($messages->isNotEmpty() && $hasInboundUnanalyzed) {
            $state = 'pending';
        }

        $project = $result['project']['matched'] ?? (is_string($result['project'] ?? null) ? $result['project'] : null);
        $employee = $result['employee']['matched'] ?? (is_string($result['employee'] ?? null) ? $result['employee'] : null);

        return [
            'state' => $state,
            'result' => $result,
            'provider' => $result['provider'] ?? $session->metadata['provider'] ?? null,
            'model' => $result['model'] ?? $session->metadata['model'] ?? null,
            'timestamp' => $sessionStamp,
            'summary' => $session->summary ?? $result['summary'] ?? (is_string($skipSummary) ? $skipSummary : null),
            'title' => $title,
            'urgency' => $result['urgency'] ?? $result['priority'] ?? null,
            'project' => $project ?: null,
            'employee' => $employee ?: null,
            'tags' => is_array($tags) ? $tags : [],
            'request_log_id' => $session->request_log_id,
            'auto_created' => (bool) $session->auto_created,
            'needs_review' => (bool) $session->needs_review,
            'task_ids' => $session->taskIdList(),
            'done' => $session->isDone(),
        ];
    }

    protected function isInbound($message): bool
    {
        $dir = $message->direction;

        if ($dir instanceof MessageDirection) {
            return $dir === MessageDirection::Inbound;
        }

        return $dir === 'inbound';
    }

    public function markSelectedRead(): void
    {
        $session = $this->selectedSession();
        if (! $session) {
            if ($this->selectedConversationId && ! $this->selectedSessionId) {
                $this->selectedSessionId = ConversationSession::query()
                    ->where('conversation_id', $this->selectedConversationId)
                    ->latest('last_message_at')
                    ->latest('id')
                    ->value('id');
                $session = $this->selectedSession();
            }
        }

        if (! $session) {
            return;
        }

        $this->selectedConversationId = $session->conversation_id;
        $session->loadMissing(['latestMessage', 'messages', 'conversation']);

        $latest = $session->latestMessage;
        $latestAt = $latest?->created_at;
        $alreadyRead = $session->last_read_at && $latestAt && $session->last_read_at->gte($latestAt);

        if (! $alreadyRead) {
            $now = now();
            $session->update(['last_read_at' => $now]);
            $session->conversation?->update(['last_read_at' => $now]);
            InboxCounts::forget();
            RailBadges::forget();
        }

        $this->acknowledgeWhatsAppRead($session);
    }

    protected function acknowledgeWhatsAppRead(ConversationSession $session): void
    {
        $inbound = $session->messages
            ->filter(fn ($message) => $this->isInbound($message))
            ->sortByDesc('created_at')
            ->first();

        $messageId = $inbound?->metadata['whatsapp_message_id'] ?? null;
        if (! is_string($messageId) || $messageId === '') {
            return;
        }

        try {
            app(WhatsAppCloudService::class)->markAsRead($messageId);
        } catch (\Throwable) {
            // Inbox read state must not depend on WhatsApp receipts.
        }
    }
}
