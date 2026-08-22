<?php

namespace Tests\Feature;

use App\Models\AiRequestLog;
use App\Services\AI\AIManager;
use Database\Seeders\EssentialPlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Events\CommunicationCreated;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Services\ConversationService;
use Modules\Customers\Models\Customer;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Events\TaskCreated;
use Modules\Tasks\Models\Task;
use Tests\TestCase;

class ConversationSessionProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EssentialPlatformSeeder::class);
        Event::fake([TaskCreated::class, CommunicationCreated::class]);
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        ]);
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123',
        ]);
    }

    public function test_one_customer_reuses_a_single_whatsapp_conversation_across_sessions(): void
    {
        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        $service = app(ConversationService::class);

        $first = $service->findOrCreateActive($customer, 'whatsapp');
        $second = $service->findOrCreateActive($customer, 'whatsapp');
        $otherCustomer = $service->findOrCreateActive(Customer::factory()->create(['name' => 'Other']), 'whatsapp');

        $this->assertSame($first->id, $second->id);
        $this->assertNotSame($first->id, $otherCustomer->id);
        $this->assertSame(1, Conversation::where('customer_id', $customer->id)->where('channel', 'whatsapp')->count());
    }

    public function test_two_cooldown_bursts_become_two_sessions_on_the_same_thread(): void
    {
        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        $website = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $ads = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'ad-system', 'status' => 'active']);

        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([
            $this->aiOutput('Fix homepage crash', 'Website Rebuild'),
            $this->aiOutput('Repair ad campaign tracker', 'ad-system'),
        ]);

        $this->inbound($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $this->inbound($conversation, 'The ads dashboard is not tracking clicks');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $conversation->refresh();
        $sessionRows = ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();
        $sessions = $conversation->metadata['sessions'] ?? [];

        $this->assertCount(1, Conversation::where('customer_id', $customer->id)->get());
        $this->assertCount(2, $sessionRows);
        $this->assertCount(2, $sessions);
        $this->assertTrue($sessionRows[0]->auto_created);
        $this->assertTrue($sessionRows[1]->auto_created);
        $this->assertNotSame($sessionRows[0]->taskIdList(), $sessionRows[1]->taskIdList());
        $this->assertSame($sessionRows[0]->id, $conversation->messages()->orderBy('id')->first()->conversation_session_id);
        $this->assertSame($sessionRows[1]->id, $conversation->messages()->orderByDesc('id')->first()->conversation_session_id);

        $firstTask = Task::find($sessionRows[0]->taskIdList()[0]);
        $secondTask = Task::find($sessionRows[1]->taskIdList()[0]);

        $this->assertSame($website->id, $firstTask->project_id);
        $this->assertSame($ads->id, $secondTask->project_id);
        $this->assertSame($conversation->id, $firstTask->metadata['conversation_id']);
        $this->assertSame($customer->id, $secondTask->metadata['customer_id']);
        $this->assertSame('Fix homepage crash', $firstTask->title);
        $this->assertSame('Repair ad campaign tracker', $secondTask->title);
        $this->assertSame('Fix homepage crash', $sessionRows[0]->title);
        $this->assertSame('Repair ad campaign tracker', $sessionRows[1]->title);
        $this->assertSame(ConversationSessionStatus::Open, $sessionRows[0]->status);
        $this->assertSame(ConversationSessionStatus::Open, $sessionRows[1]->status);
    }

    public function test_verified_extra_customer_matches_project_via_pivot(): void
    {
        $owner = Customer::factory()->create(['name' => 'Owner Co']);
        $extra = Customer::factory()->create(['name' => 'Extra Contact']);
        $project = Project::factory()->create([
            'customer_id' => $owner->id,
            'name' => 'Website Rebuild',
            'status' => 'active',
        ]);

        app(ProjectService::class)->attachCustomer($project, $extra);

        $matched = app(ProjectService::class)->matchForCustomer($extra->id, 'Website Rebuild');
        $this->assertSame($project->id, $matched?->id);

        $conversation = app(ConversationService::class)->findOrCreateActive($extra, 'whatsapp');
        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $this->inbound($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertSame($project->id, $task->project_id);
    }

    public function test_completing_all_session_tasks_marks_the_session_done(): void
    {
        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $this->inbound($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($session);
        $this->assertSame(ConversationSessionStatus::Open, $session->status);

        $task = Task::find($session->taskIdList()[0]);
        $task->update(['completed_at' => now()]);

        $this->assertTrue($session->fresh()->isDone());
        $this->assertSame(ConversationSessionStatus::Done, $session->fresh()->status);
    }

    public function test_named_project_does_not_attach_another_customers_project(): void
    {
        $other = Customer::factory()->create(['name' => 'Other Client']);
        $foreign = Project::factory()->create(['customer_id' => $other->id, 'name' => 'ad-system', 'status' => 'active']);

        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        $own = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Mobile App', 'status' => 'active']);

        $matched = app(ProjectService::class)->matchForCustomer($customer->id, 'ad-system');
        $this->assertNull($matched, 'Must not steal another customer\'s similarly named project.');

        $website = app(ProjectService::class)->matchForCustomer($customer->id, 'Website Rebuild');
        $this->assertSame($own->id, $website?->id);
        $this->assertNotSame($foreign->id, $website?->id);
    }

    public function test_many_projects_without_a_unique_ai_name_leave_the_task_unassigned(): void
    {
        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'ad-system', 'status' => 'active']);

        $this->assertNull(app(ProjectService::class)->matchForCustomer($customer->id, null));
        $this->assertNull(app(ProjectService::class)->matchForCustomer($customer->id, ''));

        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');
        $this->queueAiOutputs([$this->aiOutput('Vague request', '')]);
        $this->inbound($conversation, 'Please help with something');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertNull($task->project_id, 'With many projects and no AI match, do not guess the first project.');
    }

    public function test_single_customer_project_is_used_when_ai_omits_the_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        $only = Project::factory()->create(['customer_id' => $customer->id, 'name' => 'ad-system', 'status' => 'active']);

        $this->assertSame($only->id, app(ProjectService::class)->matchForCustomer($customer->id, '')?->id);
    }

    /**
     * @param  list<array<string, mixed>>  $outputs
     */
    protected function queueAiOutputs(array $outputs): void
    {
        $this->mock(AIManager::class, function ($mock) use ($outputs) {
            $queue = $outputs;
            $mock->shouldReceive('execute')->andReturnUsing(function () use (&$queue) {
                $parsed = array_shift($queue) ?? $this->aiOutput('Fallback task', '');

                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => $parsed,
                    'provider' => 'mock',
                    'model_name' => 'test',
                ]);
            });
        });
    }

    protected function inbound(Conversation $conversation, string $body): void
    {
        app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'sender_identifier' => '15551234567',
            'sender_name' => $conversation->customer?->name,
            'body' => $body,
            'customer_id' => $conversation->customer_id,
            'status' => 'received',
            'processing_status' => ['ai_analyzed' => false],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function aiOutput(string $title, string $project): array
    {
        return [
            'is_actionable' => true,
            'intent' => 'request',
            'confidence' => 0.92,
            'summary' => $title,
            'project' => $project,
            'tasks' => [
                [
                    'title' => $title,
                    'description' => $title,
                    'priority' => 'high',
                    'assigned_to' => '',
                ],
            ],
        ];
    }
}
