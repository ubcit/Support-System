<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;

class DailySnapshot extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'date',
        'metrics',
    ];

    protected $casts = [
        'date' => 'date',
        'metrics' => 'array',
    ];

    /**
     * Increment a specific metric dynamically.
     */
    public function incrementMetric(string $key, int $amount = 1): void
    {
        $metrics = $this->metrics ?? [];
        $metrics[$key] = ($metrics[$key] ?? 0) + $amount;
        
        $this->update(['metrics' => $metrics]);
    }
}
