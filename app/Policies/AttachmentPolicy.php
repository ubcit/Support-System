<?php

namespace App\Policies;

use App\Models\User;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Models\Message;
use Modules\Customers\Models\Customer;
use Modules\Issues\Models\Issue;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Security\Models\Role;
use Modules\Tasks\Models\Task;

/**
 * There is no dedicated "attachments.*" RBAC permission group — access to an
 * attachment is instead derived from workspace membership (File Manager /
 * Drive library) and, when present, whether the user can access the record
 * it's attached to (its `attachable`).
 *
 * Workspace membership is checked first so orphaned attachments (e.g. media
 * left behind after a Message was deleted) remain previewable in Drive, and
 * so library uploads attached to Workspace are not blocked by missing
 * attachable policies.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        if ($this->belongsToUserWorkspace($user, $attachment)) {
            return true;
        }

        return $this->canAccessAttachable($user, $attachment, 'view');
    }

    public function create(User $user, string $attachableType): bool
    {
        return $this->canAccessAttachableType($user, $attachableType, 'update');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($this->belongsToUserWorkspace($user, $attachment)) {
            return true;
        }

        // Removing an attachment is an edit to the record it's attached to
        // (adding/removing a file), not a deletion of that record itself, so
        // this intentionally checks "update" rather than "delete".
        return $this->canAccessAttachable($user, $attachment, 'update');
    }

    protected function belongsToUserWorkspace(User $user, Attachment $attachment): bool
    {
        if (! $attachment->workspace_id) {
            return false;
        }

        // Match BelongsToWorkspace: executives see across workspaces.
        if (method_exists($user, 'hasAnyRoleSlug') && $user->hasAnyRoleSlug(Role::executives())) {
            return true;
        }

        $workspaceId = $user->resolveEmployee()?->workspace_id;

        return $workspaceId !== null
            && (int) $attachment->workspace_id === (int) $workspaceId;
    }

    protected function canAccessAttachable(User $user, Attachment $attachment, string $ability): bool
    {
        $attachable = $attachment->attachable;

        if (! $attachable) {
            return false;
        }

        // Messages and Workspaces don't have their own policies; grant
        // access to any authenticated user in the same workspace.
        if ($attachable instanceof Message || $attachable instanceof Workspace) {
            return true;
        }

        return $user->can($ability, $attachable);
    }

    protected function canAccessAttachableType(User $user, string $attachableType, string $ability): bool
    {
        return match ($attachableType) {
            Task::class => $user->hasPermission('tasks.edit'),
            Project::class => $user->hasPermission('projects.manage'),
            Issue::class => $user->hasPermission('issues.manage'),
            Customer::class => $user->hasPermission('customers.manage'),
            default => false,
        };
    }
}
