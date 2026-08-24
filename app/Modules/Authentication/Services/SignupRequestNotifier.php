<?php

namespace Modules\Authentication\Services;

use App\Helpers\AppShell;
use App\Mail\SignupRequestReceivedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Services\NotificationService;

class SignupRequestNotifier
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * Notify approvers (in-app + email) that a pending signup awaits review.
     */
    public function notifyPendingSignup(User $pendingUser): void
    {
        $recipients = $this->approvers();

        if ($recipients->isEmpty()) {
            AppShell::refresh();

            return;
        }

        $title = 'New signup request';
        $body = "{$pendingUser->name} ({$pendingUser->email}) is waiting for approval.";
        $actionUrl = route('signup-requests');
        $metadata = ['user_id' => $pendingUser->id];

        foreach ($recipients as $employee) {
            $this->notifications->send(
                title: $title,
                body: $body,
                type: 'signup_request',
                employee: $employee,
                userId: $employee->user_id,
                actionUrl: $actionUrl,
                metadata: $metadata,
            );

            if ($employee->email) {
                Mail::to($employee->email)->queue(
                    new SignupRequestReceivedMail($pendingUser, $employee)
                );
            }
        }

        AppShell::refresh();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function approvers()
    {
        return Employee::query()
            ->whereNotNull('user_id')
            ->whereNotNull('email')
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', 'signup_requests.manage'))
            ->with('user')
            ->get();
    }

    public static function pendingCountFor(?User $viewer): int
    {
        if (! $viewer || ! $viewer->hasPermission('signup_requests.manage')) {
            return 0;
        }

        return (int) User::query()
            ->where('status', \App\Enums\UserApprovalStatus::Pending->value)
            ->count();
    }
}
