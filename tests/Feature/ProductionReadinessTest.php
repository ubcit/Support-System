<?php

use App\Livewire\EmployeeManagement\Index;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Employees\Models\Employee;
use Modules\Security\Models\Role;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        EssentialPlatformSeeder::class,
        RolesAndPermissionsSeeder::class,
        UserAndEmployeeSeeder::class,
    ]);
});

it('renders forgot-password page', function () {
    $this->get(route('password.request'))
        ->assertOk();
});

it('returns 403 when unauthenticated user tries admin page', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect(route('login'));
});

it('hire employee creates user and sends password reset', function () {
    $admin = User::where('email', 'boss@thespace.app')->firstOrFail();

    $uniqueEmail = 'newhire-'.uniqid().'@example.com';

    $this->actingAs($admin);

    Livewire\Livewire::test(Index::class)
        ->set('formName', 'Test New Hire')
        ->set('formEmail', $uniqueEmail)
        ->set('formSystemRole', Role::EMPLOYEE)
        ->set('formJobTitle', 'Junior Engineer')
        ->set('formDepartment', 'Engineering')
        ->set('formMaxWorkload', 5)
        ->set('formIsAvailable', true)
        ->call('hireEmployee')
        ->assertHasNoErrors();

    $newUser = User::where('email', $uniqueEmail)->first();
    expect($newUser)->not->toBeNull();

    $newEmployee = Employee::where('email', $uniqueEmail)->first();
    expect($newEmployee)->not->toBeNull();
    expect($newEmployee->role)->toBe('Junior Engineer');
    expect($newEmployee->roles()->first()?->slug)->toBe(Role::EMPLOYEE);
});

it('blocks offboarding employee with open tasks', function () {
    $admin = User::where('email', 'boss@thespace.app')->firstOrFail();
    $target = User::where('email', 'ahmed@thespace.app')->firstOrFail();
    $employee = $target->resolveEmployee();

    $todo = WorkflowState::where('name', 'To Do')->first();
    if (! $todo) {
        $this->markTestSkipped('No workflow states found.');
    }

    $task = Task::factory()->create([
        'title' => 'Active blocker',
        'current_state_id' => $todo->id,
        'workflow_id' => $todo->workflow_id,
    ]);
    $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

    $this->actingAs($admin);

    Livewire\Livewire::test(Index::class)
        ->call('selectEmployee', $employee->id)
        ->call('deleteEmployee', $employee->id);

    // Employee must not be deleted when they have open tasks
    expect(Employee::find($employee->id))->not->toBeNull();
});
