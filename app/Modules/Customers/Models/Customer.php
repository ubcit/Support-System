<?php

namespace Modules\Customers\Models;

use App\Shared\Traits\Filterable;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Issues\Models\Issue;
use Modules\MultiTenancy\Traits\BelongsToWorkspace;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class Customer extends Model
{
    use BelongsToWorkspace, Filterable, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'email',
        'phone',
        'whatsapp_id',
        'company',
        'notes',
        'preferred_locale',
        'is_active',
        'daily_ai_cost_limit',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'daily_ai_cost_limit' => 'float',
        'metadata' => 'array',
    ];

    protected array $filterable = [
        'is_active',
        'company',
    ];

    protected array $searchable = [
        'name',
        'email',
        'phone',
        'company',
    ];

    protected array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function linkedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'customer_project')
            ->withTimestamps();
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Issues attached directly, via a conversation, or via a project.
     *
     * @return Builder<Issue>
     */
    public static function linkedIssueQuery(int $customerId): Builder
    {
        return Issue::query()->where(function (Builder $query) use ($customerId) {
            $query->where('customer_id', $customerId)
                ->orWhereHas('conversation', fn (Builder $conversation) => $conversation->where('customer_id', $customerId))
                ->orWhereHas('project', function (Builder $project) use ($customerId) {
                    $project->where('customer_id', $customerId)
                        ->orWhereHas('customers', fn (Builder $linked) => $linked->where('customers.id', $customerId));
                });
        });
    }

    /**
     * Tasks created from WhatsApp, projects, issues, or session task_ids.
     *
     * @return Builder<Task>
     */
    public static function linkedTaskQuery(int $customerId): Builder
    {
        $sessionTaskIds = ConversationSession::query()
            ->whereHas('conversation', fn (Builder $query) => $query->where('customer_id', $customerId))
            ->pluck('task_ids')
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Task::query()
            ->whereNull('archived_at')
            ->where(function (Builder $query) use ($customerId, $sessionTaskIds) {
                $query->whereHas('project', function (Builder $project) use ($customerId) {
                    $project->where('customer_id', $customerId)
                        ->orWhereHas('customers', fn (Builder $linked) => $linked->where('customers.id', $customerId));
                })
                    ->orWhereHas('issue', function (Builder $issue) use ($customerId) {
                        $issue->where('customer_id', $customerId)
                            ->orWhereHas('conversation', fn (Builder $conversation) => $conversation->where('customer_id', $customerId));
                    })
                    ->orWhere(function (Builder $meta) use ($customerId) {
                        $meta->where('metadata->customer_id', $customerId)
                            ->orWhere('metadata->customer_id', (string) $customerId);
                    });

                if ($sessionTaskIds !== []) {
                    $query->orWhereIn('id', $sessionTaskIds);
                }
            });
    }
}
