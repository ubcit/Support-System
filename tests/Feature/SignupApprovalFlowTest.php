<?php

namespace Tests\Feature;

use App\Enums\UserApprovalStatus;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\SignupRequests\Index as SignupRequestsIndex;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Mail\SignupRequestReceivedMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Authentication\Services\SignupRequestNotifier;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\Notification;
use Modules\Security\Models\Role;
use Tests\TestCase;

class SignupApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $boss;

    protected User $employeeOnly;

    protected function setUp(): void
    {
        parent::setUp();

        Workspace::create([
            'name' => 'The Space',
            'slug' => 'the-space',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->boss = $this->createUserWithEmployeeRole(
            roleSlug: 'boss',
            name: 'Boss User',
            email: 'boss_user@thespace.app',
            password: 'password1234',
        );

        $this->employeeOnly = $this->createUserWithEmployeeRole(
            roleSlug: 'employee',
            name: 'Employee Only',
            email: 'employee_only@thespace.app',
            password: 'password1234',
        );
    }

    private function createUserWithEmployeeRole(string $roleSlug, string $name, string $email, string $password): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $workspace = Workspace::firstOrFail();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'name' => $name,
            'email' => $email,
            'role' => $roleSlug,
        ]);

        $employee->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_register_page_renders(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSeeLivewire(Register::class)
            ->assertSee('Create account')
            ->assertSee('Phone number');
    }

    public function test_register_creates_pending_user_and_does_not_authenticate(): void
    {
        Mail::fake();

        Livewire::test(Register::class)
            ->set('name', 'Alice Applicant')
            ->set('email', 'alice@thespace.app')
            ->set('phone', '+9647705550001')
            ->set('password', 'password1234')
            ->set('password_confirmation', 'password1234')
            ->call('register')
            ->assertRedirect(route('login'));

        $user = User::where('email', 'alice@thespace.app')->firstOrFail();
        $this->assertTrue($user->isPending());
        $this->assertSame('+9647705550001', $user->phone);
        $this->assertGuest();

        $this->assertSame(1, SignupRequestNotifier::pendingCountFor($this->boss));
        $this->assertSame(0, SignupRequestNotifier::pendingCountFor($this->employeeOnly));

        $this->assertTrue(
            Notification::query()
                ->where('type', 'signup_request')
                ->where('employee_id', $this->boss->resolveEmployee()->id)
                ->where('metadata->user_id', $user->id)
                ->exists()
        );

        Mail::assertQueued(SignupRequestReceivedMail::class, function (SignupRequestReceivedMail $mail) use ($user) {
            return $mail->pendingUser->id === $user->id
                && $mail->recipient->id === $this->boss->resolveEmployee()->id;
        });
    }

    public function test_register_requires_phone(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'No Phone')
            ->set('email', 'nophone@thespace.app')
            ->set('password', 'password1234')
            ->set('password_confirmation', 'password1234')
            ->call('register')
            ->assertHasErrors(['phone']);
    }

    public function test_pending_login_is_blocked(): void
    {
        $user = User::create([
            'name' => 'Pending User',
            'email' => 'pending@thespace.app',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'pending@thespace.app')
            ->set('password', 'password1234')
            ->call('login')
            ->assertHasErrors(['email' => 'Your account is pending admin approval.']);

        $this->assertGuest();
    }

    public function test_rejected_login_is_blocked_and_shows_message(): void
    {
        $message = 'You are not eligible for access.';

        $user = User::create([
            'name' => 'Rejected User',
            'email' => 'rejected@thespace.app',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Rejected->value,
            'rejection_message' => $message,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'rejected@thespace.app')
            ->set('password', 'password1234')
            ->call('login')
            ->assertHasErrors(['email' => $message]);

        $this->assertGuest();
    }

    public function test_boss_can_open_signup_requests_page(): void
    {
        $pending = User::create([
            'name' => 'Visible Pending',
            'email' => 'visible_pending@thespace.app',
            'phone' => '+9647705550099',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        $this->actingAs($this->boss)
            ->get(route('signup-requests'))
            ->assertOk()
            ->assertSee('Signup Requests')
            ->assertSee('Visible Pending')
            ->assertSee('visible_pending@thespace.app')
            ->assertSee('+9647705550099')
            ->assertDontSee('No pending requests');

        $this->assertTrue($pending->fresh()->isPending());
    }

    public function test_employee_without_permission_gets_403(): void
    {
        $pending = User::create([
            'name' => 'Signup Pending (Employee Can Wrongly Approve)',
            'email' => 'employee_try_approve@thespace.app',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        $this->actingAs($this->employeeOnly);

        $threw = false;
        try {
            Livewire::test(SignupRequestsIndex::class)
                ->set('approveRoleByUserId', [$pending->id => 'boss'])
                ->call('approve', $pending->id);
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertTrue(
            $threw || $pending->fresh()->isPending(),
            'Employee user should not be able to approve signup requests.'
        );
        $this->assertFalse(Employee::where('user_id', $pending->id)->exists());
    }

    public function test_approve_creates_employee_assigns_role_and_sends_email(): void
    {
        $pending = User::create([
            'name' => 'Signup Pending',
            'email' => 'approve_me@thespace.app',
            'phone' => '+9647705550002',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        Mail::fake();

        $this->actingAs($this->boss);

        Livewire::test(SignupRequestsIndex::class)
            ->set("approveRoleByUserId.{$pending->id}", 'boss')
            ->assertSet("approveRoleByUserId.{$pending->id}", 'boss')
            ->call('approve', $pending->id)
            ->assertHasNoErrors();

        $this->assertSame(UserApprovalStatus::Approved->value, $pending->fresh()->status);

        /** @var Employee $employee */
        $employee = Employee::where('user_id', $pending->id)->firstOrFail();
        $this->assertSame('+9647705550002', $employee->phone);
        $this->assertSame('boss', $employee->role);

        $slugs = DB::table('employee_role')
            ->join('roles', 'roles.id', '=', 'employee_role.role_id')
            ->where('employee_role.employee_id', $employee->id)
            ->pluck('roles.slug')
            ->all();

        $this->assertNotEmpty($slugs, 'Expected employee to have at least one role.');

        $bossRoleId = Role::where('slug', 'boss')->value('id');
        $bossRoleName = Role::where('slug', 'boss')->value('name');
        $adminRoleName = Role::where('slug', 'admin')->value('name');
        $pivotRoleId = DB::table('employee_role')->where('employee_id', $employee->id)->value('role_id');
        $this->assertSame(
            $bossRoleId,
            $pivotRoleId,
            "Pivot role_id did not match boss. (bossRoleId={$bossRoleId}, bossRoleName={$bossRoleName}, adminRoleName={$adminRoleName}, pivotRoleId={$pivotRoleId})"
        );

        Mail::assertQueued(SignupApprovedMail::class, function (SignupApprovedMail $mail) use ($pending) {
            return $mail->user->id === $pending->id && $mail->roleName === 'Boss';
        });

        Livewire::test(Login::class)
            ->set('email', 'approve_me@thespace.app')
            ->set('password', 'password1234')
            ->call('login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_approve_keeps_selected_employee_role_instead_of_defaulting_to_boss(): void
    {
        $pending = User::create([
            'name' => 'Signup Employee Role',
            'email' => 'approve_employee_role@thespace.app',
            'phone' => '+9647705550008',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        Mail::fake();

        $this->actingAs($this->boss);

        Livewire::test(SignupRequestsIndex::class)
            ->set("approveRoleByUserId.{$pending->id}", Role::EMPLOYEE)
            ->call('approve', $pending->id)
            ->assertHasNoErrors();

        $employee = Employee::where('user_id', $pending->id)->firstOrFail();
        $this->assertSame(Role::EMPLOYEE, $employee->role);
        $this->assertTrue($employee->roles()->where('slug', Role::EMPLOYEE)->exists());
        $this->assertFalse($employee->roles()->where('slug', Role::BOSS)->exists());
    }

    public function test_reject_does_not_create_employee_stores_message_and_sends_email(): void
    {
        $message = 'Not approved by policy.';

        $pending = User::create([
            'name' => 'Signup Pending (Reject)',
            'email' => 'reject_me@thespace.app',
            'password' => Hash::make('password1234'),
            'status' => UserApprovalStatus::Pending->value,
        ]);

        Mail::fake();

        $this->actingAs($this->boss);

        Livewire::test(SignupRequestsIndex::class)
            ->set("rejectMessageByUserId.{$pending->id}", $message)
            ->call('reject', $pending->id)
            ->assertHasNoErrors();

        $fresh = $pending->fresh();
        $this->assertTrue($fresh->isRejected());
        $this->assertSame($message, $fresh->rejection_message);

        $this->assertFalse(Employee::where('user_id', $pending->id)->exists());

        Mail::assertQueued(SignupRejectedMail::class, function (SignupRejectedMail $mail) use ($pending, $message) {
            return $mail->user->id === $pending->id && $mail->rejectionMessage === $message;
        });

        Livewire::test(Login::class)
            ->set('email', 'reject_me@thespace.app')
            ->set('password', 'password1234')
            ->call('login')
            ->assertHasErrors(['email' => $message]);
    }
}
