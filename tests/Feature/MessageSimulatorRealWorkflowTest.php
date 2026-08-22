<?php

namespace Tests\Feature;

use App\Livewire\MessageSimulator\Index as MessageSimulator;
use App\Livewire\TaskDetail\Index as TaskDetail;
use App\Models\AiModel;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Jobs\ProcessIncomingMessage;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Services\ConversationService;
use Modules\Communication\Support\WhatsAppInboundPayload;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerService;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class MessageSimulatorRealWorkflowTest extends TestCase
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

        $this->app['config']->set('services.whatsapp.access_token', 'test-whatsapp-token');
        $this->app['config']->set('services.whatsapp.phone_number_id', '123456');
    }

    public function test_sim_controls_apply_zero_cooldown_and_force_ai_error_metadata(): void
    {
        Queue::fake([ProcessBufferedConversation::class, SendOutboundMessage::class]);

        app(CustomerService::class)->findOrCreateByPhone('+9647709990001', [
            'name' => 'Known Sim',
            'whatsapp_id' => '+9647709990001',
            'metadata' => [
                'project_verified' => true,
                'needs_project_verification' => false,
                'boss_notes' => 'Assign to Ahmed',
            ],
        ]);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text(
                '+9647709990001',
                'Known Sim',
                'Please fix checkout',
                null,
                ['cooldown_seconds' => 0, 'force_ai_error' => true],
            ),
            'whatsapp'
        ))->handle(app(CustomerService::class), app(ConversationService::class));

        Queue::assertPushed(ProcessBufferedConversation::class, function (ProcessBufferedConversation $job) {
            return $job->cooldownSeconds === 0 && $job->isBoss === false;
        });

        $message = Message::query()->where('body', 'Please fix checkout')->firstOrFail();
        $this->assertTrue((bool) data_get($message->metadata, 'force_ai_error'));
        $this->assertSame(0, (int) data_get($message->metadata, 'sim_cooldown_seconds'));
    }

    public function test_force_ai_error_marks_session_needs_review_without_tasks(): void
    {
        Queue::fake([SendOutboundMessage::class]);

        $customer = app(CustomerService::class)->findOrCreateByPhone('+9647709990002', [
            'name' => 'Force Fail',
            'metadata' => [
                'project_verified' => true,
                'needs_project_verification' => false,
            ],
        ]);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text(
                '+9647709990002',
                'Force Fail',
                'This should fail AI',
                null,
                ['cooldown_seconds' => 0, 'force_ai_error' => true],
            ),
            'whatsapp'
        ))->handle(app(CustomerService::class), app(ConversationService::class));

        $conversation = $customer->fresh()->conversations()->latest('id')->firstOrFail();
        $session = ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        (new ProcessBufferedConversation(
            $conversation,
            $session->last_message_at?->toIso8601String() ?? now()->toIso8601String(),
            0,
            false,
            $session->id,
        ))->handle();

        $session->refresh();
        $this->assertSame(ConversationSessionStatus::NeedsReview, $session->status);
        $this->assertSame('AI Failed — Manual Triage Needed', $session->title);
        $this->assertSame([], $session->taskIdList());
    }

    public function test_unknown_then_code_passes_sim_cooldown_into_buffer_resume(): void
    {
        Queue::fake([SendOutboundMessage::class, ProcessBufferedConversation::class]);

        Project::factory()->create([
            'name' => 'Website Rebuild',
            'code' => 'AB12CD',
            'status' => 'active',
        ]);

        $service = app(CustomerService::class);
        $conversations = app(ConversationService::class);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text(
                '15551119999',
                'Guest',
                'Homepage is down',
                null,
                ['cooldown_seconds' => 0],
            ),
            'whatsapp'
        ))->handle($service, $conversations);

        Queue::assertNotPushed(ProcessBufferedConversation::class);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text(
                '15551119999',
                'Guest',
                'AB12CD',
                null,
                ['cooldown_seconds' => 0],
            ),
            'whatsapp'
        ))->handle($service, $conversations);

        Queue::assertPushed(ProcessBufferedConversation::class, function (ProcessBufferedConversation $job) {
            return $job->cooldownSeconds === 0;
        });

        $guest = Customer::where('phone', '15551119999')->firstOrFail();
        $this->assertTrue((bool) ($guest->metadata['project_verified'] ?? false));
    }

    public function test_message_simulator_defaults_mock_in_testing_and_dispatches_real_workflow(): void
    {
        Queue::fake();

        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@thespace.app'))->firstOrFail();

        Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->assertSet('ai_provider', 'mock')
            ->assertSet('instant_ai', true)
            ->call('loadScenario', 'known')
            ->assertSet('sender_mode', 'known')
            ->set('customer_message', 'The invoice page crashes when I click save.')
            ->call('sendRealWorkflow')
            ->assertHasNoErrors();

        Queue::assertPushed(ProcessIncomingMessage::class, function (ProcessIncomingMessage $job) {
            $controls = data_get($job->payload, 'entry.0.changes.0.value.sim_controls', []);

            return ($controls['ai_provider'] ?? null) === 'mock'
                && ($controls['cooldown_seconds'] ?? null) === 0;
        });

        $customer = Customer::where('phone', '+9647701234567')->firstOrFail();
        $this->assertTrue((bool) data_get($customer->metadata, 'project_verified'));
        $this->assertNotEmpty(data_get($customer->metadata, 'boss_notes'));
    }

    public function test_full_message_simulator_operations_create_tasks_and_complete_session(): void
    {
        Queue::fake([SendOutboundMessage::class]);

        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@thespace.app'))->firstOrFail();
        $project = Project::factory()->create([
            'name' => 'Ad-System',
            'code' => 'J36CSF',
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->set('ai_provider', 'mock')
            ->set('instant_ai', true)
            ->call('loadScenario', 'known')
            ->set('project', $project->name)
            ->set('customer_message', 'The invoice page crashes when I click save on Ad-System.')
            ->call('sendRealWorkflow')
            ->assertHasNoErrors();

        $phone = $component->get('customer_phone');
        $customer = Customer::where('phone', $phone)->firstOrFail();
        $conversation = $customer->conversations()->latest('id')->firstOrFail();
        $session = ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->firstOrFail();

        (new ProcessBufferedConversation(
            $conversation,
            $session->last_message_at?->toIso8601String() ?? now()->toIso8601String(),
            0,
            false,
            $session->id,
        ))->handle();

        $session->refresh();
        $this->assertSame(ConversationSessionStatus::Open, $session->status);
        $this->assertNotEmpty($session->taskIdList());

        Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->set('customer_phone', $phone)
            ->set('last_dispatched_phone', $phone)
            ->call('refreshWorkflowResults')
            ->call('markLatestSessionTasksDone')
            ->assertHasNoErrors();

        $session->refresh();
        $this->assertTrue($session->taskIdList() !== []);
        foreach (Task::query()->whereIn('id', $session->taskIdList())->get() as $task) {
            $this->assertTrue($task->isCompleted());
        }

        // Legacy ops must not throw
        Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->set('ai_provider', 'mock')
            ->call('loadScenario', 'known')
            ->call('runWithoutAi')
            ->assertHasNoErrors()
            ->assertNotSet('pipelineLog', null);

        Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->set('ai_provider', 'mock')
            ->call('loadScenario', 'known')
            ->call('runPipeline')
            ->assertHasNoErrors()
            ->assertNotSet('pipelineLog', null);

        Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->set('ai_provider', 'mock')
            ->call('loadScenario', 'known')
            ->call('simulateE2E')
            ->assertHasNoErrors()
            ->assertNotSet('e2eResults', null);

        // AI error scenario lands in Needs Review
        $err = Livewire::actingAs($admin)
            ->test(MessageSimulator::class)
            ->set('ai_provider', 'mock')
            ->call('loadScenario', 'ai_error')
            ->call('sendRealWorkflow')
            ->assertHasNoErrors();

        $errPhone = $err->get('customer_phone');
        $errCustomer = Customer::where('phone', $errPhone)->firstOrFail();
        $errConversation = $errCustomer->conversations()->latest('id')->firstOrFail();
        $errSession = ConversationSession::query()
            ->where('conversation_id', $errConversation->id)
            ->latest('id')
            ->firstOrFail();

        (new ProcessBufferedConversation(
            $errConversation,
            $errSession->last_message_at?->toIso8601String() ?? now()->toIso8601String(),
            0,
            false,
            $errSession->id,
        ))->handle();

        $errSession->refresh();
        $this->assertSame(ConversationSessionStatus::NeedsReview, $errSession->status);
    }

    public function test_workspace_default_ai_provider_remains_gemini_for_production_path(): void
    {
        $workspace = Workspace::firstOrFail();

        $this->assertSame('gemini', $workspace->settings['ai_provider'] ?? null);
        $this->assertTrue(
            AiModel::query()->where('provider', 'gemini')->where('is_active', true)->exists()
        );
        $this->assertTrue(
            AiModel::query()->where('provider', 'mock')->where('is_active', true)->exists()
        );
    }

    public function test_task_detail_shows_original_request_and_admin_conversation_link(): void
    {
        $workspace = Workspace::firstOrFail();
        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@thespace.app'))->firstOrFail();
        $employee = Employee::where('user_id', $admin->id)->firstOrFail();

        $workflow = Workflow::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'name' => 'Task',
            'entity_type' => 'task',
            'is_default' => true,
        ]);
        $todo = WorkflowState::create([
            'uuid' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'slug' => 'todo',
            'type' => 'start',
            'position' => 1,
        ]);

        $task = Task::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'type' => 'task',
            'title' => 'Fix checkout',
            'description' => "## AI summary\nBroken checkout\n\n## Customer request\nCannot pay on mobile",
            'workflow_id' => $workflow->id,
            'current_state_id' => $todo->id,
            'status' => 'todo',
            'priority' => 'medium',
            'metadata' => [
                'source' => 'customer_message',
                'conversation_id' => 42,
                'conversation_session_id' => 7,
            ],
            'created_by' => $employee->id,
        ]);

        Livewire::actingAs($admin)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->assertSee('Original WhatsApp request')
            ->assertSee('Cannot pay on mobile')
            ->assertSee('View source conversation');
    }
}
