<?php

namespace Tests\Feature;

use App\Livewire\AuditLogs\Index as AuditLogsIndex;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Audit\Models\AuditLog;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EssentialPlatformSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserAndEmployeeSeeder::class,
        ]);
    }

    public function test_customer_create_update_delete_writes_audit_logs(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $this->actingAs($boss);

        $customer = Customer::factory()->create([
            'name' => 'Audit Co',
            'workspace_id' => Workspace::firstOrFail()->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'user_id' => $boss->id,
        ]);

        $customer->update(['name' => 'Audit Co Renamed']);

        $updateLog = AuditLog::query()
            ->where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertSame('Audit Co', $updateLog->old_values['name'] ?? null);
        $this->assertSame('Audit Co Renamed', $updateLog->new_values['name'] ?? null);

        $customerId = $customer->id;
        $customer->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => Customer::class,
            'auditable_id' => $customerId,
            'user_id' => $boss->id,
        ]);
    }

    public function test_password_fields_are_redacted_in_audit_logs(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $this->actingAs($boss);

        $user = User::create([
            'name' => 'Audit User',
            'email' => 'audit-user@thespace.app',
            'password' => Hash::make('secret-password'),
        ]);

        $log = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('[redacted]', $log->new_values['password'] ?? null);
    }

    public function test_writing_audit_log_does_not_recurse(): void
    {
        $before = AuditLog::query()->count();

        AuditLog::create([
            'action' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => 1,
            'created_at' => now(),
        ]);

        $this->assertSame($before + 1, AuditLog::query()->count());
    }

    public function test_admin_can_open_audit_logs_page(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Database operations');
    }

    public function test_audit_logs_page_defaults_to_last_seven_days(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        Livewire::actingAs($boss)
            ->test(AuditLogsIndex::class)
            ->assertSet('dateFrom', now()->subDays(6)->toDateString())
            ->assertSet('dateTo', now()->toDateString());
    }

    public function test_audit_logs_user_filter_limits_results(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $this->actingAs($boss);

        $customer = Customer::factory()->create([
            'name' => 'Filter Co',
            'workspace_id' => Workspace::firstOrFail()->id,
        ]);

        $other = User::create([
            'name' => 'Other Actor',
            'email' => 'other-actor@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($boss)
            ->test(AuditLogsIndex::class)
            ->set('userId', (string) $other->id)
            ->assertSee('Other Actor');
    }

    public function test_employee_cannot_open_audit_logs_page(): void
    {
        $workspace = Workspace::firstOrFail();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->firstOrFail();

        $employeeUser = User::create([
            'name' => 'Audit Employee',
            'email' => 'audit-employee@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $employeeUser->id,
            'workspace_id' => $workspace->id,
            'name' => 'Audit Employee',
            'email' => 'audit-employee@thespace.app',
            'role' => 'Employee',
        ]);
        $employee->roles()->sync([$employeeRole->id]);

        $this->actingAs($employeeUser)
            ->get('/admin/audit-logs')
            ->assertRedirect(route('workspace.dashboard'));
    }
}
