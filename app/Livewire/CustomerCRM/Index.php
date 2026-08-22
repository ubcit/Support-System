<?php

namespace App\Livewire\CustomerCRM;

use App\Livewire\Concerns\AuthorizesActions;
use Livewire\Component;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerAiBudgetService;
use Modules\Issues\Enums\IssueStatus;

class Index extends Component
{
    use AuthorizesActions;

    public const LIST_LIMIT = 8;

    public ?int $selectedCustomerId = null;

    public string $searchQuery = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public string $formName = '';

    public string $formEmail = '';

    public string $formPhone = '';

    public string $formDailyAiCostLimit = '';

    protected function queryString(): array
    {
        return [
            'selectedCustomerId' => ['as' => 'customer', 'except' => null],
        ];
    }

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $id;
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(?int $customerId = null): void
    {
        $id = $customerId ?? $this->selectedCustomerId;
        $customer = Customer::find($id);
        if (! $customer) {
            return;
        }

        $this->formName = $customer->name ?? '';
        $this->formEmail = $customer->email ?? '';
        $this->formPhone = $customer->phone ?? '';
        $this->formDailyAiCostLimit = $customer->daily_ai_cost_limit === null ? '' : (string) $customer->daily_ai_cost_limit;
        $this->showEditModal = true;
    }

    public function newCustomer(): void
    {
        $this->authorizePermission('customers.manage');
        $this->validate([
            'formName' => 'required|string|max:255',
            'formEmail' => 'nullable|email',
            'formPhone' => 'nullable|string|max:50',
            'formDailyAiCostLimit' => 'nullable|numeric|min:0',
        ]);

        $customer = Customer::create([
            'name' => $this->formName,
            'email' => $this->formEmail ?: null,
            'phone' => $this->formPhone ?: null,
            'daily_ai_cost_limit' => trim($this->formDailyAiCostLimit) === '' ? null : (float) $this->formDailyAiCostLimit,
        ]);

        $this->selectedCustomerId = $customer->id;
        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('success', 'Customer created successfully');
    }

    public function editCustomer(): void
    {
        $this->authorizePermission('customers.manage');
        $customer = Customer::find($this->selectedCustomerId);
        if (! $customer) {
            return;
        }

        $this->validate([
            'formName' => 'required|string|max:255',
            'formEmail' => 'nullable|email',
            'formPhone' => 'nullable|string|max:50',
            'formDailyAiCostLimit' => 'nullable|numeric|min:0',
        ]);

        $customer->update([
            'name' => $this->formName,
            'email' => $this->formEmail ?: null,
            'phone' => $this->formPhone ?: null,
            'daily_ai_cost_limit' => trim($this->formDailyAiCostLimit) === '' ? null : (float) $this->formDailyAiCostLimit,
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        session()->flash('success', 'Customer Updated');
    }

    public function deleteCustomer(int $id): void
    {
        $this->authorizePermission('customers.manage');
        $customer = Customer::find($id);
        if ($customer) {
            $customer->delete();
            if ($this->selectedCustomerId === $id) {
                $this->selectedCustomerId = null;
            }
            session()->flash('success', 'Customer deleted.');
        }
    }

    protected function resetForm(): void
    {
        $this->formName = '';
        $this->formEmail = '';
        $this->formPhone = '';
        $this->formDailyAiCostLimit = '';
    }

    public function render()
    {
        $query = Customer::withCount(['conversations']);

        if (trim($this->searchQuery) !== '') {
            $search = '%'.$this->searchQuery.'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('company', 'like', $search);
            });
        }

        $customers = $query->orderBy('name')->get();

        $selectedCustomer = null;
        if ($this->selectedCustomerId) {
            $selectedCustomer = Customer::find($this->selectedCustomerId);
        } else {
            $selectedCustomer = $customers->first();
            if ($selectedCustomer) {
                $this->selectedCustomerId = $selectedCustomer->id;
            }
        }

        $conversations = collect();
        $issues = collect();
        $tasks = collect();
        $projects = collect();
        $sessions = collect();
        $stats = [
            'open_tasks' => 0,
            'overdue_tasks' => 0,
            'issues' => 0,
            'open_issues' => 0,
            'sessions' => 0,
            'needs_review' => 0,
            'inbound' => 0,
            'outbound' => 0,
            'ai_spent_today' => 0.0,
            'ai_limit' => null,
            'task_total' => 0,
            'issue_total' => 0,
            'last_message_at' => null,
        ];

        if ($selectedCustomer) {
            $id = $selectedCustomer->id;

            $conversations = Conversation::query()
                ->where('customer_id', $id)
                ->with(['latestMessage', 'latestSession'])
                ->latest('last_message_at')
                ->get();

            $allIssues = Customer::linkedIssueQuery($id)->latest()->get();
            $allTasks = Customer::linkedTaskQuery($id)
                ->with(['currentState', 'project'])
                ->latest()
                ->get();

            $issues = $allIssues->take(self::LIST_LIMIT);
            $tasks = $allTasks->take(self::LIST_LIMIT);

            $projects = $selectedCustomer->projects()->latest()->get();

            $sessionQuery = ConversationSession::query()
                ->whereHas('conversation', fn ($q) => $q->where('customer_id', $id));

            $sessions = (clone $sessionQuery)
                ->latest('last_message_at')
                ->limit(self::LIST_LIMIT)
                ->get();

            $messageCounts = Message::query()
                ->where('customer_id', $id)
                ->toBase()
                ->selectRaw('direction, COUNT(*) as total')
                ->groupBy('direction')
                ->pluck('total', 'direction');

            $budget = app(CustomerAiBudgetService::class);
            $openIssueStatuses = [
                IssueStatus::New,
                IssueStatus::Open,
                IssueStatus::InProgress,
                IssueStatus::Waiting,
            ];

            $stats = [
                'open_tasks' => $allTasks->filter(fn ($task) => ! $task->isCompleted())->count(),
                'overdue_tasks' => $allTasks->filter(fn ($task) => $task->isOverdue())->count(),
                'issues' => $allIssues->count(),
                'open_issues' => $allIssues->filter(fn ($issue) => in_array($issue->status, $openIssueStatuses, true))->count(),
                'sessions' => (clone $sessionQuery)->count(),
                'needs_review' => (clone $sessionQuery)->where(function ($q) {
                    $q->where('needs_review', true)
                        ->orWhere('status', ConversationSessionStatus::NeedsReview);
                })->count(),
                'inbound' => (int) ($messageCounts['inbound'] ?? 0),
                'outbound' => (int) ($messageCounts['outbound'] ?? 0),
                'ai_spent_today' => $budget->spentToday($selectedCustomer),
                'ai_limit' => $budget->effectiveLimit($selectedCustomer),
                'task_total' => $allTasks->count(),
                'issue_total' => $allIssues->count(),
                'last_message_at' => $conversations->max('last_message_at'),
            ];
        }

        return view('livewire.customer-c-r-m.index', [
            'customers' => $customers,
            'selected_customer' => $selectedCustomer,
            'conversations' => $conversations,
            'issues' => $issues,
            'tasks' => $tasks,
            'projects' => $projects,
            'sessions' => $sessions,
            'stats' => $stats,
        ]);
    }
}
