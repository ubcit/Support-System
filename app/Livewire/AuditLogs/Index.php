<?php

namespace App\Livewire\AuditLogs;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Audit\Models\AuditLog;

class Index extends Component
{
    use WithPagination;

    private const DEFAULT_DATE_DAYS = 6;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $action = null;

    #[Url]
    public ?string $modelType = null;

    #[Url]
    public ?string $userId = null;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public ?int $selectedLogId = null;

    public function mount(): void
    {
        $this->applyDefaultDatesIfEmpty();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function updatingModelType(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function updatedAction(mixed $value): void
    {
        $this->action = filled($value) ? (string) $value : null;
    }

    public function updatedModelType(mixed $value): void
    {
        $this->modelType = filled($value) ? (string) $value : null;
    }

    public function updatedUserId(mixed $value): void
    {
        $this->userId = filled($value) ? (string) $value : null;
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function selectLog(int $logId): void
    {
        $this->selectedLogId = $logId;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->action = null;
        $this->modelType = null;
        $this->userId = null;
        $this->applyDefaultDates();
        $this->resetPage();
    }

    public function getSelectedLogProperty(): ?AuditLog
    {
        if (! $this->selectedLogId) {
            return null;
        }

        return AuditLog::with(['user', 'employee'])->find($this->selectedLogId);
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with(['user', 'employee'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('auditable_type', 'like', $term)
                        ->orWhere('action', 'like', $term)
                        ->orWhere('url', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhere('old_values', 'like', $term)
                        ->orWhere('new_values', 'like', $term);
                });
            })
            ->when(filled($this->action), fn ($query) => $query->where('action', $this->action))
            ->when(filled($this->modelType), function ($query) {
                $query->where(function ($q) {
                    $q->where('auditable_type', $this->modelType)
                        ->orWhere('auditable_type', 'like', '%\\'.$this->modelType);
                });
            })
            ->when(filled($this->userId), fn ($query) => $query->where('user_id', $this->userId))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest('created_at')
            ->paginate(20);

        $modelTypeOptions = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->mapWithKeys(fn (string $type) => [class_basename($type) => class_basename($type)])
            ->all();

        $userOptions = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user) => [
                (string) $user->id => trim($user->name.' · '.$user->email),
            ])
            ->all();

        return view('livewire.audit-logs.index', [
            'logs' => $logs,
            'modelTypeOptions' => $modelTypeOptions,
            'userOptions' => $userOptions,
            'selectedLog' => $this->selectedLog,
            'actionOptions' => [
                'created' => 'Created',
                'updated' => 'Updated',
                'deleted' => 'Deleted',
                'restored' => 'Restored',
                'force_deleted' => 'Force deleted',
            ],
        ]);
    }

    protected function applyDefaultDatesIfEmpty(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->applyDefaultDates();
        }
    }

    protected function applyDefaultDates(): void
    {
        $this->dateTo = now()->toDateString();
        $this->dateFrom = now()->subDays(self::DEFAULT_DATE_DAYS)->toDateString();
    }
}
