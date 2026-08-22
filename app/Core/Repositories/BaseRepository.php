<?php

namespace App\Core\Repositories;

use App\Core\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements RepositoryInterface
{
    protected Builder $query;

    protected array $withRelations = [];

    public function __construct(protected Model $model)
    {
        $this->resetQuery();
    }

    /**
     * Reset the query builder instance.
     */
    protected function resetQuery(): void
    {
        $this->query = $this->model->newQuery();
    }

    /**
     * Apply eager loading and return a fresh query.
     */
    public function newQuery(): Builder
    {
        $query = $this->model->newQuery();

        if (!empty($this->withRelations)) {
            $query->with($this->withRelations);
            $this->withRelations = [];
        }

        return $query;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->newQuery()->get($columns);
    }

    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->find($id, $columns);
    }

    public function findByUuid(string $uuid, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->where('uuid', $uuid)->first($columns);
    }

    public function findByUuidOrFail(string $uuid, array $columns = ['*']): Model
    {
        return $this->newQuery()->where('uuid', $uuid)->firstOrFail($columns);
    }

    public function create(array $data): Model
    {
        // Refresh so any DB-level column defaults not present in $data
        // (e.g. a `status` string column with a default that isn't part of
        // every create payload) are reflected in-memory immediately,
        // matching update()'s existing ->fresh() behavior below. Without
        // this, callers referencing e.g. $model->status right after create()
        // see the PHP-side null rather than the persisted default.
        return $this->model->create($data)->fresh();
    }

    public function update(int|string $id, array $data): Model
    {
        $record = $this->model->findOrFail($id);
        $record->update($data);

        return $record->fresh();
    }

    public function delete(int|string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage, $columns);
    }

    public function findWhere(array $conditions, array $columns = ['*']): Collection
    {
        return $this->newQuery()->where($conditions)->get($columns);
    }

    public function findFirstWhere(array $conditions, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->where($conditions)->first($columns);
    }

    public function with(array $relations): static
    {
        $this->withRelations = $relations;

        return $this;
    }

    public function count(): int
    {
        return $this->newQuery()->count();
    }
}
