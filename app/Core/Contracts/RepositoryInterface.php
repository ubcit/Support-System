<?php

namespace App\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Find a record by its primary key.
     */
    public function find(int|string $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by UUID.
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model;

    /**
     * Find a record by UUID or fail.
     */
    public function findByUuidOrFail(string $uuid, array $columns = ['*']): Model;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update an existing record.
     */
    public function update(int|string $id, array $data): Model;

    /**
     * Delete a record.
     */
    public function delete(int|string $id): bool;

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find records matching conditions.
     */
    public function findWhere(array $conditions, array $columns = ['*']): Collection;

    /**
     * Find first record matching conditions.
     */
    public function findFirstWhere(array $conditions, array $columns = ['*']): ?Model;

    /**
     * Get records with relationships.
     */
    public function with(array $relations): static;

    /**
     * Count all records.
     */
    public function count(): int;

    /**
     * Get a fresh query builder.
     */
    public function newQuery(): \Illuminate\Database\Eloquent\Builder;
}
