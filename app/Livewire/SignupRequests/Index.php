<?php

namespace App\Livewire\SignupRequests;

use App\Enums\UserApprovalStatus;
use App\Livewire\Concerns\AuthorizesActions;
use App\Models\User;
use Livewire\Component;
use Modules\Authentication\Services\SignupApprovalService;
use Modules\Security\Models\Role;

class Index extends Component
{
    use AuthorizesActions;

    /**
     * Selected approver role per user id.
     *
     * @var array<int, string>
     */
    public array $approveRoleByUserId = [];

    /**
     * Rejection message per user id.
     *
     * @var array<int, string>
     */
    public array $rejectMessageByUserId = [];

    public string $defaultRoleSlug = '';

    public function mount(): void
    {
        $this->authorizePermission('signup_requests.manage');

        $this->defaultRoleSlug = $this->resolveDefaultRoleSlug();
    }

    public function approve(int $userId, SignupApprovalService $approvalService): void
    {
        $this->authorizePermission('signup_requests.manage');

        $pendingUser = User::findOrFail($userId);
        if ($pendingUser->status !== UserApprovalStatus::Pending->value) {
            session()->flash('error', 'This request is no longer pending.');

            return;
        }

        $allowedRoleSlugs = $this->allowedRoleSlugs();
        $roleSlug = $this->approveRoleByUserId[$userId]
            ?? $this->approveRoleByUserId[(string) $userId]
            ?? $this->defaultRoleSlug
            ?: $this->resolveDefaultRoleSlug();

        if (! in_array($roleSlug, $allowedRoleSlugs, true)) {
            $this->addError('approveRole', 'Please choose a valid role before approving.');

            return;
        }

        $approvalService->approve($pendingUser, $roleSlug, auth()->user());

        unset($this->approveRoleByUserId[$userId], $this->approveRoleByUserId[(string) $userId], $this->rejectMessageByUserId[$userId], $this->rejectMessageByUserId[(string) $userId]);

        session()->flash('success', 'User approved successfully.');
    }

    public function reject(int $userId, SignupApprovalService $approvalService): void
    {
        $this->authorizePermission('signup_requests.manage');

        $pendingUser = User::findOrFail($userId);
        if ($pendingUser->status !== UserApprovalStatus::Pending->value) {
            session()->flash('error', 'This request is no longer pending.');

            return;
        }

        $message = trim((string) ($this->rejectMessageByUserId[$userId]
            ?? $this->rejectMessageByUserId[(string) $userId]
            ?? ''));
        if ($message === '') {
            $this->addError('rejectMessage', 'Rejection message is required.');

            return;
        }

        $approvalService->reject($pendingUser, $message, auth()->user());

        unset($this->approveRoleByUserId[$userId], $this->approveRoleByUserId[(string) $userId], $this->rejectMessageByUserId[$userId], $this->rejectMessageByUserId[(string) $userId]);

        session()->flash('success', 'User rejected successfully.');
    }

    public function render()
    {
        $pendingUsers = User::query()
            ->where('status', UserApprovalStatus::Pending->value)
            ->orderByDesc('created_at')
            ->get();

        if ($this->defaultRoleSlug === '') {
            $this->defaultRoleSlug = $this->resolveDefaultRoleSlug();
        }

        foreach ($pendingUsers as $user) {
            $hasIntKey = array_key_exists($user->id, $this->approveRoleByUserId);
            $hasStringKey = array_key_exists((string) $user->id, $this->approveRoleByUserId);
            if (! $hasIntKey && ! $hasStringKey) {
                $this->approveRoleByUserId[$user->id] = $this->defaultRoleSlug;
            }
        }

        $roles = Role::where('slug', '!=', 'customer')
            ->orderBy('name')
            ->get();

        return view('livewire.signup-requests.index', [
            'pendingUsers' => $pendingUsers,
            'roles' => $roles,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function allowedRoleSlugs(): array
    {
        return Role::where('slug', '!=', 'customer')
            ->orderBy('name')
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->values()
            ->all();
    }

    protected function resolveDefaultRoleSlug(): string
    {
        $allowed = $this->allowedRoleSlugs();

        if (in_array(Role::EMPLOYEE, $allowed, true)) {
            return Role::EMPLOYEE;
        }

        return $allowed[0] ?? Role::EMPLOYEE;
    }
}
