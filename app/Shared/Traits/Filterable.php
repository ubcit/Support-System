<?php

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides standardized filtering, sorting, and searching
 * for Eloquent models via query parameters.
 *
 * Usage: Add `use Filterable;` to your model.
 */
trait Filterable
{
    /**
     * Define the fields that can be filtered.
     * Override in your model to customize.
     *
     * @return array<string>
     */
    public function getFilterableFields(): array
    {
        return $this->filterable ?? [];
    }

    /**
     * Define the fields that can be sorted.
     * Override in your model to customize.
     *
     * @return array<string>
     */
    public function getSortableFields(): array
    {
        return $this->sortable ?? ['created_at', 'updated_at'];
    }

    /**
     * Define the fields that are searchable.
     * Override in your model to customize.
     *
     * @return array<string>
     */
    public function getSearchableFields(): array
    {
        return $this->searchable ?? [];
    }

    /**
     * Scope: apply filters from request parameters.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $filterableFields = $this->getFilterableFields();

        foreach ($filters as $field => $value) {
            if (!in_array($field, $filterableFields)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif ($value === 'null') {
                $query->whereNull($field);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Scope: apply sorting.
     */
    public function scopeSort(Builder $query, ?string $sortBy = null, string $direction = 'desc'): Builder
    {
        $sortableFields = $this->getSortableFields();

        if ($sortBy && in_array($sortBy, $sortableFields)) {
            $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';
            $query->orderBy($sortBy, $direction);
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * Scope: apply search across searchable fields.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $searchableFields = $this->getSearchableFields();

        if (empty($searchableFields)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($searchableFields, $term) {
            foreach ($searchableFields as $field) {
                $q->orWhere($field, 'LIKE', "%{$term}%");
            }
        });
    }
}
