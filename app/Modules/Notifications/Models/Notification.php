<?php

namespace Modules\Notifications\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;

class Notification extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'employee_id',
        'user_id',
        'type',
        'title',
        'body',
        'channel',
        'action_url',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
