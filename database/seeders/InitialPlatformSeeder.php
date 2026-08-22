<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Communication\Models\Conversation;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Milestone;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Models\WorkflowTransition;

class InitialPlatformSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Workflow & States
        $workflow = Workflow::firstOrCreate(
            ['name' => 'Software Development Workflow'],
            [
                'entity_type' => 'task',
                'is_default' => true,
            ]
        );

        $todo = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'To Do'],
            ['type' => 'initial', 'order' => 1]
        );

        $inProgress = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'In Progress'],
            ['type' => 'active', 'order' => 2]
        );

        $review = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'Code Review'],
            ['type' => 'active', 'order' => 3]
        );

        $done = WorkflowState::firstOrCreate(
            ['workflow_id' => $workflow->id, 'name' => 'Done'],
            ['type' => 'completed', 'order' => 4]
        );

        // Register transitions
        WorkflowTransition::firstOrCreate([
            'workflow_id' => $workflow->id,
            'from_state_id' => $todo->id,
            'to_state_id' => $inProgress->id,
        ]);

        WorkflowTransition::firstOrCreate([
            'workflow_id' => $workflow->id,
            'from_state_id' => $inProgress->id,
            'to_state_id' => $review->id,
        ]);

        WorkflowTransition::firstOrCreate([
            'workflow_id' => $workflow->id,
            'from_state_id' => $review->id,
            'to_state_id' => $done->id,
        ]);

        // 2. Create Sample Projects
        $customer = Customer::first() ?? Customer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Default Client',
            'email' => 'client@thespace.app',
            'phone' => '+9647700009999',
        ]);

        $project = Project::firstOrCreate(
            ['name' => 'Al Noor Clinic ERP'],
            [
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'description' => 'Complete Healthcare ERP & Queue Management System',
                'workflow_id' => $workflow->id,
            ]
        );

        // 3. Create Milestone
        $milestone = Milestone::firstOrCreate(
            ['name' => 'v1.0 Pilot Launch', 'project_id' => $project->id],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Pilot deployment for Clinic Branch 1',
                'start_date' => now()->startOfMonth(),
                'due_date' => now()->endOfMonth(),
                'status' => 'active',
            ]
        );

        // 4. Create Initial Tasks
        $boss = Employee::where('email', 'boss@thespace.app')->first();
        $ahmed = Employee::where('email', 'ahmed@thespace.app')->first();

        $task1 = Task::firstOrCreate(
            ['title' => 'Build Native Web Platform Dashboards'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Implement Boss Dashboard, Multi-View Task Dashboard, and WhatsApp Live Inbox.',
                'project_id' => $project->id,
                'milestone_id' => $milestone->id,
                'workflow_id' => $workflow->id,
                'current_state_id' => $inProgress->id,
                'priority' => 'urgent',
                'due_date' => now()->addDays(2),
                'estimated_hours' => 12.0,
                'created_by' => $boss?->id,
            ]
        );

        if ($ahmed && $task1->assignees->count() === 0) {
            $task1->assignments()->create([
                'employee_id' => $ahmed->id,
                'assigned_by' => $boss?->id,
                'assigned_at' => now(),
            ]);
        }

        $task2 = Task::firstOrCreate(
            ['title' => 'Set up RBAC Permissions Matrix'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Configure roles (CEO, Boss, Manager, Developer, QA, Support) and assign permissions.',
                'project_id' => $project->id,
                'milestone_id' => $milestone->id,
                'workflow_id' => $workflow->id,
                'current_state_id' => $done->id,
                'priority' => 'high',
                'due_date' => now()->subDay(),
                'completed_at' => now(),
                'estimated_hours' => 4.0,
                'created_by' => $boss?->id,
            ]
        );

        // 5. Seed Customer Conversation
        $customer = Customer::first();
        if ($customer) {
            $conversation = Conversation::firstOrCreate(
                ['customer_id' => $customer->id, 'channel' => 'whatsapp'],
                [
                    'uuid' => (string) Str::uuid(),
                    'status' => 'open',
                    'updated_at' => now(),
                ]
            );

            if ($conversation->messages()->count() === 0) {
                $conversation->messages()->create([
                    'uuid' => (string) Str::uuid(),
                    'channel' => 'whatsapp',
                    'direction' => 'inbound',
                    'sender_identifier' => $customer->phone,
                    'body' => 'Hello, can you help update the queue system for branch 4?',
                    'status' => 'received',
                    'sender_name' => $customer->name,
                ]);

                $conversation->messages()->create([
                    'uuid' => (string) Str::uuid(),
                    'channel' => 'whatsapp',
                    'direction' => 'outbound',
                    'sender_identifier' => 'boss@thespace.app',
                    'body' => 'Hello! Our technical team is reviewing your request now.',
                    'status' => 'processed',
                    'sender_name' => 'Yousif (Boss)',
                ]);
            }
        }
    }
}
