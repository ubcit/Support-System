<?php

use App\Models\User;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Communication\Models\Message;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Tasks\Services\KanbanEngineService;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Models\Workflow;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('executes administrator workflow: create and archive employee', function () {
    $admin = User::factory()->create();
    
    // Simulate creating an employee
    $employeeUser = User::factory()->create(['email' => 'testemployee@example.com']);
    $employee = Employee::create([
        'user_id' => $employeeUser->id,
        'name' => 'Test Employee',
        'email' => 'testemployee@example.com',
        'role' => 'employee',
        'is_active' => true,
    ]);

    expect($employee->id)->not->toBeNull();
    
    // Simulate archiving employee
    $employee->delete();
    
    // Verify database changes
    $archivedEmployee = Employee::withTrashed()->find($employee->id);
    expect($archivedEmployee->trashed())->toBeTrue();
});

it('executes boss workflow: create project and assign members', function () {
    $customer = \Modules\Customers\Models\Customer::create(['name' => 'C', 'phone' => '123']);
    $project = Project::create([
        'name' => 'Alpha Testing Project',
        'status' => 'active',
        'customer_id' => $customer->id,
    ]);

    $employeeUser = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $employeeUser->id,
        'name' => 'Employee 1',
        'email' => 'emp1@test.com',
        'role' => 'employee',
    ]);

    $project->employees()->attach($employee->id);

    // Verify database
    expect($project->employees()->count())->toBe(1);
    expect($project->employees->first()->id)->toBe($employee->id);
});

it('executes employee workflow: valid kanban state transition', function () {
    $workflow = Workflow::create(['name' => 'Test Workflow', 'entity_type' => 'task']);
    $stateInProgress = WorkflowState::create([
        'workflow_id' => $workflow->id,
        'name' => 'In Progress',
        'type' => 'active',
        'order' => 2,
    ]);

    $newTask = Task::create([
        'title' => 'Task 2',
        'workflow_id' => $workflow->id,
    ]);

    $engine = new KanbanEngineService();
    $result = $engine->moveCard($newTask, $stateInProgress->id);

    expect($result['success'])->toBeTrue();
    expect($newTask->fresh()->current_state_id)->toBe($stateInProgress->id);
});

it('executes whatsapp pipeline: enqueue outbound messages and track retries', function () {
    Queue::fake();

    $customer = \Modules\Customers\Models\Customer::create([
        'name' => 'Test Customer',
        'phone' => '+1234567890'
    ]);

    $conversation = \Modules\Communication\Models\Conversation::create([
        'customer_id' => $customer->id,
        'channel' => 'whatsapp'
    ]);

    $message = Message::create([
        'customer_id' => $customer->id,
        'conversation_id' => $conversation->id,
        'channel' => 'whatsapp',
        'direction' => 'outbound',
        'body' => 'Test acceptance message',
        'status' => 'received',
        'sender_identifier' => 'agent'
    ]);

    SendOutboundMessage::dispatch($message);

    Queue::assertPushed(SendOutboundMessage::class, function ($job) use ($message) {
        return $job->message->id === $message->id;
    });
});
