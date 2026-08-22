<?php

namespace Tests\Feature;

use App\Helpers\AvatarPalette;
use App\Livewire\Profile\Index as Profile;
use App\Livewire\TaskDashboard\Index as TaskDashboard;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected User $boss;

    protected Employee $bossEmployee;

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

        $this->boss = User::create([
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $this->bossEmployee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->boss->id,
            'workspace_id' => $workspace->id,
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'role' => 'Executive Boss',
        ]);
        $this->bossEmployee->roles()->sync([$bossRole->id]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);
    }

    public function test_profile_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->boss)
            ->test(Profile::class)
            ->assertSee('Change profile photo')
            ->set('avatar', UploadedFile::fake()->image('portrait.jpg'))
            ->assertHasNoErrors()
            ->assertSee('Profile photo updated.');

        $this->boss->refresh();
        $this->assertNotNull($this->boss->avatar_path);
        Storage::disk('public')->assertExists($this->boss->avatar_path);

        Livewire::actingAs($this->boss)
            ->test(Profile::class)
            ->call('removeAvatar')
            ->assertSee('Profile photo removed.');

        $this->boss->refresh();
        $this->assertNull($this->boss->avatar_path);
    }

    public function test_assignee_initials_use_varied_colors_and_show_photo_when_set(): void
    {
        Storage::fake('public');

        $this->boss->avatar_path = UploadedFile::fake()->image('boss.jpg')->store('users/avatars', 'public');
        $this->boss->save();

        $workspace = Workspace::firstOrFail();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $saraUser = User::create([
            'name' => 'Sara Ahmed',
            'email' => 'sara@thespace.app',
            'password' => Hash::make('password123'),
        ]);
        $sara = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $saraUser->id,
            'workspace_id' => $workspace->id,
            'name' => 'Sara Ahmed',
            'email' => 'sara@thespace.app',
            'role' => 'Employee',
        ]);
        $sara->roles()->sync([$employeeRole->id]);

        $task = Task::factory()->create([
            'title' => 'Shared assignee task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $this->bossEmployee->id, 'assigned_at' => now()]);
        $task->assignments()->create(['employee_id' => $sara->id, 'assigned_at' => now()]);

        $html = Livewire::actingAs($this->boss)
            ->test(TaskDashboard::class)
            ->html();

        $this->assertStringContainsString($this->boss->avatarUrl(), $html);
        $this->assertStringContainsString(AvatarPalette::initials('Sara Ahmed', 1), $html);
        $this->assertStringContainsString($sara->avatarColorClasses(), $html);
        $this->assertNotSame($this->bossEmployee->avatarColorClasses(), $sara->avatarColorClasses());
        $this->assertStringNotContainsString('bg-amber-500 text-white flex items-center justify-center text-[10px]', $html);
    }
}
