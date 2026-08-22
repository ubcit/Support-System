<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;
use Tests\TestCase;

class EmployeeAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $boss;

    protected User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Workspace::create([
            'name' => 'The Space',
            'slug' => 'the-space',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $workspace = Workspace::firstOrFail();
        $bossRole = Role::where('slug', Role::BOSS)->firstOrFail();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->firstOrFail();

        $this->boss = User::create([
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $bossEmployee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->boss->id,
            'workspace_id' => $workspace->id,
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'role' => 'Executive Boss',
        ]);
        $bossEmployee->roles()->sync([$bossRole->id]);

        $this->employeeUser = User::create([
            'name' => 'Yassen',
            'email' => 'yassen@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->employeeUser->id,
            'workspace_id' => $workspace->id,
            'name' => 'Yassen',
            'email' => 'yassen@thespace.app',
            'role' => 'Employee',
        ]);
        $employee->roles()->sync([$employeeRole->id]);
    }

    public function test_employee_login_redirects_to_workspace_dashboard(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'yassen@thespace.app')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('workspace.dashboard'));

        $this->assertAuthenticatedAs($this->employeeUser);
    }

    public function test_employee_is_redirected_from_admin_pages_to_workspace(): void
    {
        $this->actingAs($this->employeeUser);

        foreach ([
            '/admin/dashboard',
            '/admin/employee-management',
            '/admin/conversation-center',
        ] as $uri) {
            $this->get($uri)->assertRedirect(route('workspace.dashboard'));
        }
    }

    public function test_employee_can_open_workspace_pages(): void
    {
        $this->actingAs($this->employeeUser);

        $this->get('/workspace')->assertOk();
        $this->get('/workspace/my-tasks')->assertOk();
    }

    public function test_boss_still_can_open_admin_dashboard(): void
    {
        $this->actingAs($this->boss)
            ->get('/admin/dashboard')
            ->assertOk();
    }
}
