<?php

namespace Modules\MultiTenancy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;

trait BelongsToWorkspace
{
    /**
     * Boot the trait: auto-stamp `workspace_id` on create, and scope every
     * read/update/delete query to the current user's workspace.
     *
     * Design notes (see docs/COMPREHENSIVE_AUDIT_2026-08-03.md / Phase 3):
     *  - Console/queue/artisan contexts (no authenticated user) are left
     *    unrestricted: these are trusted, internal code paths (seeders,
     *    scheduled jobs, webhook handlers resolving a workspace explicitly),
     *    not untrusted API/browser requests.
     *  - Executive roles (`admin`/`boss`) intentionally bypass the scope and
     *    see across all workspaces.
     *  - An authenticated user with no linked Employee (and therefore no
     *    workspace) sees nothing for these models, rather than leaking every
     *    tenant's data — deny-by-default.
     */
    protected static function bootBelongsToWorkspace(): void
    {
        static::creating(function ($model) {
            if (! is_null($model->workspace_id)) {
                return;
            }

            $workspaceId = static::resolveCurrentWorkspaceId();

            if ($workspaceId) {
                $model->workspace_id = $workspaceId;
            }
        });

        static::addGlobalScope('workspace', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            if (method_exists($user, 'hasAnyRoleSlug') && $user->hasAnyRoleSlug(Role::executives())) {
                return;
            }

            $workspaceId = static::resolveCurrentWorkspaceId();

            if ($workspaceId) {
                $builder->where($builder->getModel()->getTable().'.workspace_id', $workspaceId);
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    /**
     * The authenticated user's workspace, resolved via their Employee
     * record. Returns null when there's no authenticated user or the user
     * has no linked Employee (e.g. an admin-only account).
     */
    protected static function resolveCurrentWorkspaceId(): ?int
    {
        if (! Auth::check()) {
            return null;
        }

        $user = Auth::user();

        if (! method_exists($user, 'resolveEmployee')) {
            return null;
        }

        return $user->resolveEmployee()?->workspace_id;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
