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

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Workspace::create([
            'name' => 'The Space',
            'slug' => 'the-space',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $bossRole = Role::where('slug', 'boss')->first();
        $user = User::create([
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'workspace_id' => Workspace::first()->id,
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'role' => 'Executive Boss',
        ]);

        if ($bossRole) {
            $employee->roles()->sync([$bossRole->id]);
        }
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeLivewire(Login::class)
            ->assertSee('Sign In')
            ->assertSee('Keep me logged in')
            ->assertSee('Sign Up')
            ->assertSee(route('register'), false);
    }

    public function test_livewire_login_succeeds_for_boss(): void
    {
        $user = User::where('email', 'boss@thespace.app')->firstOrFail();

        Livewire::test(Login::class)
            ->set('email', 'boss@thespace.app')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_livewire_login_fails_with_bad_password(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'boss@thespace.app')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
