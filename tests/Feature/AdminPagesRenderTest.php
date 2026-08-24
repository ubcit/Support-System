<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customers\Models\Customer;
use Tests\TestCase;

class AdminPagesRenderTest extends TestCase
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

    public function test_boss_can_render_core_admin_pages(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $uris = [
            '/admin/dashboard',
            '/admin/employee-management',
            '/admin/signup-requests',
            '/admin/customer-crm',
            '/admin/project-hub',
            '/admin/task-dashboard',
            '/admin/conversation-center',
            '/admin/ai-center',
            '/admin/rules-center',
            '/admin/reports-hub',
            '/admin/customer-ai-limits',
            '/admin/workspace-settings',
            '/admin/benchmark-dashboard',
            '/admin/boss-workspace',
            '/admin/global-timeline',
            '/admin/message-simulator',
            '/admin/laravel-logs',
            '/admin/worker-logs',
            '/admin/operations-dashboard',
            '/admin/prompt-playground',
        ];

        foreach ($uris as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_conversation_explorer_redirects_to_conversation_center(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/admin/conversation-explorer')
            ->assertRedirect('/admin/conversation-center');
    }

    public function test_kanban_and_schedule_redirect_into_my_tasks_views(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $this->get('/admin/kanban-board')
            ->assertRedirect('/admin/task-dashboard?view=board');

        $this->get('/admin/schedule')
            ->assertRedirect('/admin/task-dashboard?view=calendar');
    }

    public function test_settings_rail_opens_workspace_settings_and_menu_says_my_tasks(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Native Task Engine')
            ->assertSee('My Tasks')
            ->assertSee('/admin/workspace-settings', false)
            ->assertSee('aria-label="Settings"', false);

        $this->get('/admin/workspace-settings')
            ->assertOk()
            ->assertSee('Workspace Settings')
            ->assertSee('Workspace Onboarding');

        $this->get('/admin/employee-management')
            ->assertOk()
            ->assertSee('Employee Hub')
            ->assertSee('aria-label="Edit profile"', false)
            ->assertSee('aria-label="Offboard Employee"', false)
            ->assertDontSee('bg-red-50 hover:bg-red-100', false);
    }

    public function test_customer_toolbar_pairs_matching_edit_and_delete_buttons(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        Customer::factory()->create(['name' => 'Acme Tools']);

        $this->actingAs($boss)
            ->get('/admin/customer-crm')
            ->assertOk()
            ->assertSee('aria-label="Edit customer"', false)
            ->assertSee('aria-label="Delete Customer"', false)
            ->assertDontSee('bg-red-50 hover:bg-red-100', false)
            ->assertSee('dark:bg-gray-800', false);
    }

    public function test_customers_and_employee_hub_are_first_class_rail_destinations(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Customers"', false)
            ->assertSee('aria-label="Employees"', false)
            ->assertSee('aria-label="File Manager"', false)
            ->assertSee('/admin/customer-crm', false)
            ->assertSee('/admin/employee-management', false);

        $customers = $this->get('/admin/customer-crm')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="Customers"\s+aria-current="page"/',
            $customers->getContent()
        );

        $employees = $this->get('/admin/employee-management')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="Employees"\s+aria-current="page"/',
            $employees->getContent()
        );
        $employees->assertSee('Employee Hub');
    }

    public function test_file_manager_is_a_first_class_rail_destination(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('aria-label="File Manager"', false)
            ->assertSee('/admin/file-manager', false)
            ->assertSee('/admin/file-manager/drive', false);

        $files = $this->get('/admin/file-manager')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="File Manager"\s+aria-current="page"/',
            $files->getContent()
        );
        $files->assertSee('File Manager')
            ->assertSee('Drive')
            ->assertSee('Open Drive');

        $drive = $this->get('/admin/file-manager/drive')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="File Manager"\s+aria-current="page"/',
            $drive->getContent()
        );
        $drive->assertSee('Drive')
            ->assertSee('Browse workspace files with inline previews')
            ->assertSee('Library');
    }

    public function test_reports_files_and_operations_land_in_their_rail_sections(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $reports = $this->get('/admin/reports-hub')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="Home"\s+aria-current="page"/',
            $reports->getContent()
        );
        $reports->assertSee('Reports & Analytics')
            ->assertSee('Insights');

        $ops = $this->get('/admin/operations-dashboard')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="AI Center"\s+aria-current="page"/',
            $ops->getContent()
        );
        $ops->assertSee('Operations Dashboard');

        $timeline = $this->get('/admin/global-timeline')->assertOk();
        $this->assertMatchesRegularExpression(
            '/aria-label="More"\s+aria-current="page"/',
            $timeline->getContent()
        );
        $timeline->assertSee('Global Timeline')
            ->assertSee('Tools');
    }

    public function test_rail_landings_compact_page_titles_into_the_subtitle(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $inbox = $this->get('/admin/conversation-center')->assertOk();
        $inbox->assertSee('Session chat and customer context')
            ->assertSee('Start Conversation');
        $this->assertDoesNotMatchRegularExpression('/<h2[^>]*>\s*Inbox\s*<\/h2>/', $inbox->getContent());

        $tasks = $this->get('/admin/task-dashboard')->assertOk();
        $tasks->assertSee('All projects')
            ->assertSee('New Task');
        $this->assertDoesNotMatchRegularExpression('/<h2[^>]*>\s*My Tasks\s*<\/h2>/', $tasks->getContent());

        $ai = $this->get('/admin/ai-center')->assertOk();
        $ai->assertSee('Models, prompts, schemas, and playground');
        $this->assertDoesNotMatchRegularExpression('/<h2[^>]*>\s*AI Center\s*<\/h2>/', $ai->getContent());

        $reports = $this->get('/admin/reports-hub')->assertOk();
        $reports->assertSee('Insights across tasks, customers, employees, and AI spend')
            ->assertSee('Open Tasks');
        $this->assertDoesNotMatchRegularExpression('/<h2[^>]*>\s*Reports &amp; Analytics\s*<\/h2>/', $reports->getContent());

        $settings = $this->get('/admin/workspace-settings')->assertOk();
        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Workspace Settings\s*<\/h2>/', $settings->getContent());
    }
}
