<?php

namespace App\Livewire\CustomerAiLimits;

use Livewire\Component;
use Modules\Communication\Models\ConversationSession;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerAiBudgetService;
use Modules\MultiTenancy\Models\Workspace;

class Index extends Component
{
    public string $defaultLimit = '';

    public array $customerLimits = [];

    public string $searchQuery = '';

    public function mount(): void
    {
        $default = app(CustomerAiBudgetService::class)->workspaceDefaultLimit();
        $this->defaultLimit = $default === null ? '' : (string) $default;
    }

    public function saveDefault(): void
    {
        $this->validate([
            'defaultLimit' => 'nullable|numeric|min:0',
        ]);

        $workspace = Workspace::query()->first();
        if (! $workspace) {
            return;
        }

        $settings = $workspace->settings ?? [];
        $value = trim($this->defaultLimit);
        $settings['default_daily_ai_cost_limit'] = $value === '' ? null : (float) $value;
        $workspace->update(['settings' => $settings]);

        session()->flash('success', 'Workspace default daily AI limit saved.');
    }

    public function saveCustomer(int $id): void
    {
        $customer = Customer::query()->find($id);
        if (! $customer) {
            return;
        }

        $raw = trim((string) ($this->customerLimits[$id] ?? ''));
        $this->validate([
            "customerLimits.{$id}" => 'nullable|numeric|min:0',
        ]);

        $customer->update([
            'daily_ai_cost_limit' => $raw === '' ? null : (float) $raw,
        ]);

        session()->flash('success', $customer->name.' daily AI limit saved.');
    }

    public function render()
    {
        $budget = app(CustomerAiBudgetService::class);
        $query = Customer::query()->orderBy('name');
        if (trim($this->searchQuery) !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->searchQuery.'%')
                    ->orWhere('phone', 'like', '%'.$this->searchQuery.'%');
            });
        }
        $customers = $query->get();

        foreach ($customers as $customer) {
            if (! array_key_exists($customer->id, $this->customerLimits)) {
                $this->customerLimits[$customer->id] = $customer->daily_ai_cost_limit === null
                    ? ''
                    : (string) $customer->daily_ai_cost_limit;
            }
        }

        $reviewCounts = ConversationSession::query()
            ->join('conversations', 'conversations.id', '=', 'conversation_sessions.conversation_id')
            ->where('conversation_sessions.metadata->skip_reason', CustomerAiBudgetService::SKIP_REASON)
            ->where('conversation_sessions.created_at', '>=', now()->startOfDay())
            ->selectRaw('conversations.customer_id, COUNT(*) as total')
            ->groupBy('conversations.customer_id')
            ->pluck('total', 'customer_id');

        $rows = $customers->map(function (Customer $customer) use ($budget, $reviewCounts) {
            $limit = $budget->effectiveLimit($customer);
            $spent = $budget->spentToday($customer);

            return [
                'customer' => $customer,
                'effective_limit' => $limit,
                'spent' => $spent,
                'remaining' => $budget->remaining($customer),
                'exhausted' => $budget->isExhausted($customer),
                'uses_default' => $customer->daily_ai_cost_limit === null && $limit !== null,
                'review_count' => (int) ($reviewCounts[$customer->id] ?? 0),
            ];
        });

        return view('livewire.customer-ai-limits.index', [
            'rows' => $rows,
            'budget' => $budget,
        ]);
    }
}
