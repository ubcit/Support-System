<?php

namespace Modules\Issues\Models;

use App\Shared\Traits\Filterable;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Attachments\Models\Attachment;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Issues\Enums\IssuePriority;
use Modules\Issues\Enums\IssueSource;
use Modules\Issues\Enums\IssueStatus;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class Issue extends Model
{
    use \Modules\MultiTenancy\Traits\BelongsToWorkspace, HasFactory, HasUuid, Filterable, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'title',
        'description',
        'conversation_id',
        'project_id',
        'customer_id',
        'reported_by',
        'assigned_to',
        'workflow_id',
        'current_state_id',
        'status',
        'priority',
        'source',
        'ai_summary',
        'ai_metadata',
        'resolved_at',
        'closed_at',
        'due_date',
        'metadata',
    ];

    protected $casts = [
        'status' => IssueStatus::class,
        'priority' => IssuePriority::class,
        'source' => IssueSource::class,
        'ai_metadata' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'due_date' => 'date',
    ];

    protected array $filterable = [
        'status',
        'priority',
        'source',
        'project_id',
        'customer_id',
        'assigned_to',
        'reported_by',
    ];

    protected array $searchable = [
        'title',
        'description',
        'ai_summary',
    ];

    protected array $sortable = [
        'title',
        'status',
        'priority',
        'due_date',
        'created_at',
        'updated_at',
        'resolved_at',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(\Modules\Communication\Models\Conversation::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(IssueTimeline::class)->orderBy('created_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
