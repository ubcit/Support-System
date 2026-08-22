<?php

namespace App\Livewire\RulesCenter;

use App\Livewire\Concerns\AuthorizesActions;
use Livewire\Component;
use Modules\Rules\Models\BusinessRule;

class Index extends Component
{
    use AuthorizesActions;

    public bool $showCreateModal = false;

    public string $name = '';

    public string $event_trigger = 'CommunicationCreated';

    public string $description = '';

    public int $is_active = 1;

    /**
     * Event names the Rules Engine can actually receive, keyed by the value
     * stored on the rule. These must match `class_basename()` of the domain
     * events wired up in `ModuleServiceProvider::boot()` — anything else can
     * never be evaluated because `EvaluateRules::handle()` matches on it.
     */
    protected const EVENT_CONDITION_FIELDS = [
        'CommunicationCreated' => 'communication.content',
        'TaskCreated' => 'task.priority',
        'TaskStateChanged' => 'toState.name',
        'IssueStateChanged' => 'toState.name',
        'SystemHealthDegraded' => 'newStatus',
    ];

    public function openCreateModal(): void
    {
        $this->reset(['name', 'description']);
        $this->event_trigger = 'CommunicationCreated';
        $this->is_active = 1;
        $this->showCreateModal = true;
    }

    public function createRule(): void
    {
        $this->authorizePermission('rules.manage');
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_trigger' => ['required', 'string', 'in:' . implode(',', array_keys(self::EVENT_CONDITION_FIELDS))],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        // The description field doubles as the free-text value the rule
        // checks for on its trigger's default field (e.g. a keyword the
        // inbound message must contain, or the state name a task/issue must
        // move into). Defaults to always-match if left blank.
        $field = self::EVENT_CONDITION_FIELDS[$this->event_trigger];
        $value = trim($this->description);

        $conditions = $value === ''
            ? []
            : ['field' => $field, 'operator' => 'contains', 'value' => $value];

        BusinessRule::create([
            'name' => $this->name,
            'event_name' => $this->event_trigger,
            'conditions' => $conditions,
            'actions' => ['type' => 'assign_employee', 'target' => 'first_available'],
            'is_active' => (bool) $this->is_active,
        ]);

        $this->showCreateModal = false;
        session()->flash('success', 'Automation Rule Created');
    }

    public function toggleRuleStatus(int $ruleId): void
    {
        $rule = BusinessRule::find($ruleId);
        if ($rule) {
            $rule->update(['is_active' => ! $rule->is_active]);
            session()->flash('success', 'Rule Status Updated');
        }
    }

    public function deleteRule($ruleId): void
    {
        $this->authorizePermission('rules.manage');
        $rule = BusinessRule::find($ruleId);
        if ($rule) {
            $rule->delete();
            session()->flash('success', 'Rule Deleted');
        }
    }

    public function mount(): void
    {
        //
    }

    public function render()
    {
        return view('livewire.rules-center.index', [
            'rules' => BusinessRule::all(),
        ]);
    }
}
