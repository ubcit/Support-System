<?php

namespace Modules\Authentication\Services;

use App\Enums\UserApprovalStatus;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;

class SignupApprovalService
{
    /**
     * Approve a pending signup: create Employee + attach RBAC Role.
     */
    public function approve(User $pendingUser, string $roleSlug, User $reviewer): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        DB::transaction(function () use ($pendingUser, $role, $reviewer) {
            $pendingUser->refresh();

            if (! $pendingUser->isPending()) {
                return;
            }

            $workspace = $reviewer->resolveEmployee()?->workspace ?? Workspace::first();
            if (! $workspace) {
                throw new \RuntimeException('No workspace found for approval.');
            }

            $employee = Employee::create([
                'workspace_id' => $workspace->id,
                'user_id' => $pendingUser->id,
                'name' => $pendingUser->name,
                'email' => $pendingUser->email,
                // Store the slug so fuzzy role checks (boss/manager/admin) keep working.
                'role' => $role->slug,
                'phone' => $pendingUser->phone,
                'department' => null,
                'is_available' => true,
                'max_workload' => 10,
                'metadata' => null,
            ]);

            $employee->roles()->syncWithoutDetaching([$role->id]);

            $pendingUser->forceFill([
                'status' => UserApprovalStatus::Approved->value,
                'rejection_message' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
        });

        // Queued notification to the user's email address.
        // (Mail classes are implemented in the emails step.)
        Mail::to($pendingUser->email)->queue(
            new SignupApprovedMail(
                user: $pendingUser,
                roleName: $role->name ?? $role->slug,
                reviewerName: $reviewer->name,
            )
        );
    }

    /**
     * Reject a pending signup without creating an Employee.
     */
    public function reject(User $pendingUser, string $message, User $reviewer): void
    {
        DB::transaction(function () use ($pendingUser, $message, $reviewer) {
            $pendingUser->refresh();

            if (! $pendingUser->isPending()) {
                return;
            }

            $pendingUser->forceFill([
                'status' => UserApprovalStatus::Rejected->value,
                'rejection_message' => $message,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
        });

        // Queued notification to the user's email address.
        // (Mail classes are implemented in the emails step.)
        Mail::to($pendingUser->email)->queue(
            new SignupRejectedMail(
                user: $pendingUser,
                reviewerName: $reviewer->name,
                rejectionMessage: $message,
            )
        );
    }
}
