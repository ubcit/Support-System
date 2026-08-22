<?php

namespace Modules\Issues\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Issues\Models\Issue;

class IssueRepository extends BaseRepository implements IssueRepositoryInterface
{
    public function __construct(Issue $model)
    {
        parent::__construct($model);
    }

    public function getByProject(int $projectId): Collection
    {
        return $this->newQuery()->where('project_id', $projectId)->get();
    }

    public function getByCustomer(int $customerId): Collection
    {
        return $this->newQuery()->where('customer_id', $customerId)->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->newQuery()->where('status', $status)->get();
    }

    public function getByAssignee(int $employeeId): Collection
    {
        return $this->newQuery()->where('assigned_to', $employeeId)->get();
    }

    public function getOverdue(): Collection
    {
        return $this->newQuery()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get();
    }
}
