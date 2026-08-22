<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPagesSmokeTest extends TestCase
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

    public function test_guest_visiting_admin_dashboard_redirects_to_login(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_boss_can_load_key_admin_routes(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $routes = [
            '/admin/dashboard',
            '/admin/boss-workspace',
            '/admin/operations-dashboard',
            '/admin/task-dashboard',
            '/admin/project-hub',
            '/admin/customer-crm',
            '/admin/employee-management',
            '/admin/conversation-center',
            '/admin/ai-center',
            '/admin/rules-center',
            '/admin/reports-hub',
            '/admin/customer-ai-limits',
            '/admin/workspace-settings',
        ];

        foreach ($routes as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_authenticated_support_employee_can_load_workspace(): void
    {
        $support = User::where('email', 'ali@thespace.app')->firstOrFail();

        $this->actingAs($support);

        $this->get('/workspace')
            ->assertOk()
            ->assertDontSee('Native Task Engine')
            ->assertSee('/workspace/profile', false)
            ->assertDontSee('/admin/workspace-settings', false);
    }

    public function test_authenticated_qa_employee_can_load_workspace(): void
    {
        $qa = User::where('email', 'sara@thespace.app')->firstOrFail();

        $this->actingAs($qa);

        $this->get('/workspace')->assertOk();
    }
}
