<?php

namespace App\Shared\Traits;

use Illuminate\Support\Str;

/**
 * Automatically generates a UUID when a model is created.
 *
 * Usage: Add `use HasUuid;` to your model.
 * Requires a `uuid` column in the migration.
 */
trait HasUuid
{
    /**
     * Boot the HasUuid trait.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key name for Laravel route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to find a model by UUID.
     */
    public function scopeByUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
