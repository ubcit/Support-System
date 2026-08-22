<?php

namespace Tests\Feature;

use App\Livewire\CustomerAiLimits\Index as CustomerAiLimits;
use App\Livewire\ReportsHub\Index as ReportsHub;
use App\Models\AiRequestLog;
use App\Models\User;
use App\Services\AI\AIManager;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Jobs\ProcessIncomingMessage;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Services\ConversationService;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerAiBudgetService;
use Modules\Customers\Services\CustomerService;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskTimeLog;
use Tests\TestCase;

class CustomerAiBudgetTest extends TestCase
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

    public function test_zero_daily_limit_skips_ai_and_marks_session_for_manual_review(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Budget Harbor Co',
            'daily_ai_cost_limit' => 0,
        ]);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');
        $this->inbound($conversation, 'Please fix the homepage');

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->never();
        });

        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(ConversationSessionStatus::NeedsReview, $session->status);
        $this->assertTrue($session->needs_review);
        $this->assertSame(CustomerAiBudgetService::SKIP_REASON, $session->metadata['skip_reason'] ?? null);
        $this->assertSame('Manual review (daily AI budget)', $session->title);
        $this->assertSame(0, AiRequestLog::query()->where('customer_id', $customer->id)->count());
    }

    public function test_spend_under_limit_calls_ai_and_stamps_customer_session_and_project(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Spend Harbor Co',
            'daily_ai_cost_limit' => 5,
        ]);
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Website Rebuild',
            'status' => 'active',
        ]);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');
        $this->inbound($conversation, 'The homepage crashes on save');

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () {
                $args = func_get_args();
                $context = $args[6] ?? [];
                $parsed = [
                    'is_actionable' => true,
                    'intent' => 'request',
                    'confidence' => 0.92,
                    'summary' => 'Fix homepage crash',
                    'project' => 'Website Rebuild',
                    'tasks' => [[
                        'title' => 'Fix homepage crash',
                        'description' => 'Fix homepage crash',
                        'priority' => 'high',
                        'assigned_to' => '',
                    ]],
                ];

                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => $parsed,
                    'provider' => 'mock',
                    'model_name' => 'test',
                    'cost' => 0.12,
                    'total_tokens' => 80,
                    'customer_id' => $context['customer_id'] ?? null,
                    'conversation_id' => $context['conversation_id'] ?? null,
                    'conversation_session_id' => $context['conversation_session_id'] ?? null,
                    'source' => $context['source'] ?? null,
                ]);
            });
        });

        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $log = AiRequestLog::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame($customer->id, $log->customer_id);
        $this->assertSame($conversation->id, $log->conversation_id);
        $this->assertSame($session->id, $log->conversation_session_id);
        $this->assertSame('conversation', $log->source);
        $this->assertSame($project->id, $log->fresh()->project_id);
        $this->assertSame($log->id, $session->request_log_id);
        $this->assertNotSame(ConversationSessionStatus::NeedsReview, $session->status);
    }

    public function test_boss_bypass_analyzes_when_customer_is_over_limit(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Boss Harbor Co',
            'daily_ai_cost_limit' => 0,
        ]);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');
        $this->inbound($conversation, 'Create a task now');

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () {
                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => [
                        'is_actionable' => true,
                        'intent' => 'request',
                        'confidence' => 0.95,
                        'summary' => 'Boss task',
                        'project' => 'Website Rebuild',
                        'tasks' => [[
                            'title' => 'Boss task',
                            'description' => 'Boss task',
                            'priority' => 'high',
                            'assigned_to' => '',
                        ]],
                    ],
                    'provider' => 'mock',
                    'model_name' => 'test',
                    'cost' => 0.2,
                ]);
            });
        });

        (new ProcessBufferedConversation(
            $conversation->fresh(),
            $conversation->fresh()->last_message_at->toIso8601String(),
            300,
            true
        ))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertNotSame(ConversationSessionStatus::NeedsReview, $session->status);
        $this->assertNotSame(CustomerAiBudgetService::SKIP_REASON, $session->metadata['skip_reason'] ?? null);
        $this->assertNotNull($session->request_log_id);
    }

    public function test_yesterdays_spend_does_not_block_today(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Rollover Harbor Co',
            'daily_ai_cost_limit' => 1,
        ]);
        $yesterday = AiRequestLog::create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'mock',
            'model_name' => 'test',
            'cost' => 4.5,
            'customer_id' => $customer->id,
        ]);
        $yesterday->created_at = now()->subDay();
        $yesterday->save();

        $this->assertFalse(app(CustomerAiBudgetService::class)->isExhausted($customer));

        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');
        $this->inbound($conversation, 'Need a small change');

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () {
                $args = func_get_args();
                $context = $args[6] ?? [];

                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => [
                        'is_actionable' => false,
                        'intent' => 'question',
                        'confidence' => 0.4,
                        'summary' => 'No action',
                        'project' => '',
                        'tasks' => [],
                    ],
                    'provider' => 'mock',
                    'model_name' => 'test',
                    'cost' => 0.05,
                    'customer_id' => $context['customer_id'] ?? null,
                ]);
            });
        });

        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $this->assertSame(2, AiRequestLog::query()->where('customer_id', $customer->id)->count());
    }

    public function test_workspace_default_limit_applies_when_customer_limit_is_blank(): void
    {
        $workspace = Workspace::query()->firstOrFail();
        $settings = $workspace->settings ?? [];
        $settings['default_daily_ai_cost_limit'] = 2.5;
        $workspace->update(['settings' => $settings]);

        $customer = Customer::factory()->create(['daily_ai_cost_limit' => null]);
        $budget = app(CustomerAiBudgetService::class);

        $this->assertSame(2.5, $budget->effectiveLimit($customer));
        $this->assertFalse($budget->isExhausted($customer));
    }

    public function test_incoming_whatsapp_skips_buffer_when_daily_limit_is_empty(): void
    {
        Bus::fake([ProcessBufferedConversation::class]);

        $customer = Customer::factory()->create([
            'name' => 'Inbound Budget Co',
            'phone' => '15559870001',
            'daily_ai_cost_limit' => 0,
        ]);

        $job = new ProcessIncomingMessage($this->whatsappPayload($customer->phone, $customer->name, 'Please help'), 'whatsapp');
        $job->handle(app(CustomerService::class), app(ConversationService::class));

        Bus::assertNotDispatched(ProcessBufferedConversation::class);

        $session = ConversationSession::query()
            ->whereHas('conversation', fn ($q) => $q->where('customer_id', $customer->id))
            ->firstOrFail();
        $this->assertSame(ConversationSessionStatus::NeedsReview, $session->status);
        $this->assertSame(CustomerAiBudgetService::SKIP_REASON, $session->metadata['skip_reason'] ?? null);
    }

    public function test_reports_hub_tabs_show_customer_employee_and_ai_cost(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employee = Employee::factory()->create(['name' => 'Report Ahmed']);
        $customer = Customer::factory()->create(['name' => 'Report Harbor Co', 'daily_ai_cost_limit' => 3]);
        $project = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Ads Rebuild', 'status' => 'active']);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Fix ads tracker',
            'completed_at' => now(),
            'priority' => 'high',
        ]);
        $task->assignments()->create([
            'employee_id' => $employee->id,
            'assigned_at' => now(),
        ]);
        TaskTimeLog::create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'duration_minutes' => 90,
            'is_running' => false,
        ]);
        $session = ConversationSession::create([
            'conversation_id' => app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp')->id,
            'status' => ConversationSessionStatus::Open,
            'title' => 'Ads session',
            'task_ids' => [$task->id],
        ]);
        $log = AiRequestLog::create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'mock',
            'model_name' => 'test',
            'cost' => 1.25,
            'total_tokens' => 400,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'conversation_session_id' => $session->id,
            'source' => 'conversation',
        ]);
        $session->update(['request_log_id' => $log->id]);

        $this->actingAs($boss);

        Livewire::test(ReportsHub::class)
            ->assertSee('Completion Rate')
            ->assertSee('AI Spend Today')
            ->set('tab', 'employees')
            ->assertSee('Report Ahmed')
            ->set('tab', 'customers')
            ->assertSee('Report Harbor Co')
            ->set('tab', 'ai-cost')
            ->assertSee('Ads Rebuild')
            ->assertSee('Ads session')
            ->assertSee('Manage daily limits');
    }

    public function test_customer_ai_limits_page_saves_customer_and_workspace_default(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Limit Harbor Co']);

        $this->actingAs($boss);

        Livewire::test(CustomerAiLimits::class)
            ->assertSee('Limit Harbor Co')
            ->set('defaultLimit', '4.5')
            ->call('saveDefault')
            ->set('customerLimits.'.$customer->id, '1.25')
            ->call('saveCustomer', $customer->id)
            ->assertSee('AI on');

        $this->assertSame(1.25, (float) $customer->fresh()->daily_ai_cost_limit);
        $this->assertEquals(4.5, Workspace::query()->first()->settings['default_daily_ai_cost_limit']);
    }

    protected function inbound($conversation, string $body): void
    {
        app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'sender_identifier' => '15551234567',
            'sender_name' => $conversation->customer?->name,
            'body' => $body,
            'customer_id' => $conversation->customer_id,
            'status' => 'received',
            'processing_status' => ['ai_analyzed' => false, 'media_downloaded' => true],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function whatsappPayload(string $phone, string $name, string $body): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '000000000000000',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [['profile' => ['name' => $name], 'wa_id' => $phone]],
                        'messages' => [[
                            'from' => $phone,
                            'id' => 'wamid.'.uniqid(),
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }
}
