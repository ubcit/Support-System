<?php

namespace Modules\Projects\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Projects\Models\Project;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function __construct(Project $model)
    {
        parent::__construct($model);
    }

    public function findByAlias(string $alias): ?Project
    {
        return $this->newQuery()
            ->whereHas('aliases', function ($query) use ($alias) {
                $query->where('alias', 'LIKE', "%{$alias}%");
            })
            ->first();
    }

    public function getByCustomer(int $customerId): Collection
    {
        return $this->newQuery()->where('customer_id', $customerId)->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->newQuery()->where('status', $status)->get();
    }
}
