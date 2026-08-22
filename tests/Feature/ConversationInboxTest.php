<?php

namespace Tests\Feature;

use App\Helpers\AppShell;
use App\Helpers\InboxCounts;
use App\Livewire\AppShell\InboxPanel;
use App\Livewire\AppShell\RailBadge;
use App\Livewire\ConversationCenter\Index as ConversationCenter;
use App\Livewire\Header\Notifications;
use App\Models\AiRequestLog;
use App\Models\User;
use App\Services\AI\AIManager;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\ProcessBufferedConversation;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Services\ConversationService;
use Modules\Customers\Models\Customer;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class ConversationInboxTest extends TestCase
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

    public function test_inbox_sidebar_groups_sessions_under_customers_and_filters_unread_and_done(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $unreadCustomer = Customer::factory()->create(['name' => 'Unread Harbor Co']);
        $doneCustomer = Customer::factory()->create(['name' => 'Closed Harbor Co']);

        $this->inboundSession($unreadCustomer, 'Need a website fix');
        [$doneConversation, $doneSession] = $this->inboundSession($doneCustomer, 'Old closed thread');
        $doneSession->update(['status' => ConversationSessionStatus::Done, 'title' => 'Old closed thread']);

        $this->actingAs($boss);

        Livewire::test(InboxPanel::class)
            ->assertSee('Unread Harbor Co')
            ->assertSee('Closed Harbor Co')
            ->assertSee('Search customers')
            ->assertSee('Done')
            ->assertDontSee('Live WhatsApp Inbox');

        Livewire::test(InboxPanel::class)
            ->set('tab', 'unread')
            ->assertSee('Unread Harbor Co')
            ->assertDontSee('Closed Harbor Co');

        Livewire::test(InboxPanel::class)
            ->set('tab', 'done')
            ->assertSee('Closed Harbor Co')
            ->assertDontSee('Unread Harbor Co');

        Livewire::test(InboxPanel::class)
            ->set('tab', 'closed')
            ->assertSee('Closed Harbor Co')
            ->assertDontSee('Unread Harbor Co');
    }

    public function test_inbox_all_count_matches_sessions_with_a_live_conversation(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Ghost Harbor Co']);
        [$conversation] = $this->inboundSession($customer, 'Please keep me visible');

        $this->actingAs($boss);

        $this->assertSame(1, InboxCounts::panel('all')['all']);
        $this->assertCount(1, InboxCounts::panel('all')['customers']);

        Livewire::test(InboxPanel::class)
            ->assertSee('Ghost Harbor Co');

        $conversation->delete();

        $this->assertSame(0, InboxCounts::panel('all')['all']);
        $this->assertSame([], InboxCounts::panel('all')['customers']);

        Livewire::test(InboxPanel::class)
            ->assertSee('No customers here');
    }

    public function test_inbox_marks_sessions_whose_tasks_are_waiting_for_manager_review(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Review Wait Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $this->inboundOn($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $task = Task::find($session->taskIdList()[0]);
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $task->update(['current_state_id' => $review->id]);

        $otherCustomer = Customer::factory()->create(['name' => 'Open Harbor Co']);
        $this->inboundSession($otherCustomer, 'Need a logo refresh');

        $this->actingAs($boss);

        Livewire::test(InboxPanel::class)
            ->assertSee('Review Wait Co')
            ->assertSee('Open Harbor Co')
            ->assertSee('Review');

        Livewire::test(InboxPanel::class)
            ->set('tab', 'review')
            ->assertSee('Review Wait Co')
            ->assertDontSee('Open Harbor Co');

        Livewire::test(ConversationCenter::class)
            ->set('filterTab', 'review')
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->assertSee('Waiting for approval')
            ->assertSee('Approve &amp; done', false)
            ->assertSee('Sessions waiting for manager approval');
    }

    public function test_inbox_customer_drill_down_lists_open_sessions_before_done(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Split Harbor Co']);

        [$conversation, $openSession] = $this->inboundSession($customer, 'Need a website fix');
        $openSession->update(['title' => 'Website still open']);

        ConversationSession::create([
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Done,
            'title' => 'Finished ads work',
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay(),
            'last_message_at' => now()->subHour(),
            'task_ids' => [],
        ]);

        $this->actingAs($boss);

        Livewire::test(InboxPanel::class)
            ->assertSee('Split Harbor Co')
            ->assertDontSee('Website still open')
            ->assertDontSee('Finished ads work');

        $this->get(route('conversation-center', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Split Harbor Co')
            ->assertSee('In progress')
            ->assertSee('Website still open')
            ->assertSee('Done')
            ->assertSee('Finished ads work')
            ->assertSeeHtml('aria-expanded')
            ->assertDontSee('AI Thread Copilot');

        Livewire::test(InboxPanel::class)
            ->set('tab', 'done')
            ->assertSee('Split Harbor Co');

        $this->get(route('conversation-center', ['tab' => 'done', 'customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Finished ads work')
            ->assertDontSee('Website still open');
    }

    public function test_opening_one_session_does_not_show_another_sessions_messages_or_copilot(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Yousif Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'ad-system', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([
            $this->aiOutput('Fix homepage crash', 'Website Rebuild'),
            $this->aiOutput('Repair ad campaign tracker', 'ad-system'),
        ]);

        $this->inboundOn($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();
        $this->inboundOn($conversation, 'The ads dashboard is not tracking clicks');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $sessions = ConversationSession::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $sessions);

        $this->actingAs($boss);

        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $sessions[0]->id)
            ->assertSee('The homepage crashes on save')
            ->assertDontSee('The ads dashboard is not tracking clicks')
            ->assertSee('Fix homepage crash');

        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $sessions[1]->id)
            ->assertSee('The ads dashboard is not tracking clicks')
            ->assertDontSee('The homepage crashes on save')
            ->assertSee('Repair ad campaign tracker');

        Livewire::test(InboxPanel::class)
            ->assertSee('Yousif Co')
            ->assertDontSee('Fix homepage crash')
            ->assertDontSee('Repair ad campaign tracker');

        $this->get(route('conversation-center', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Fix homepage crash')
            ->assertSee('Repair ad campaign tracker');
    }

    public function test_completing_session_tasks_lists_the_session_under_done(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Done Harbor Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $this->inboundOn($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        Task::find($session->taskIdList()[0])->update(['completed_at' => now()]);

        $this->assertTrue($session->fresh()->isDone());

        $this->actingAs($boss)
            ->get(route('conversation-center', ['tab' => 'done']))
            ->assertOk()
            ->assertSee('Done Harbor Co')
            ->assertDontSee('Fix homepage crash');

        $this->get(route('conversation-center', ['tab' => 'done', 'customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Done Harbor Co')
            ->assertSee('Fix homepage crash');
    }

    public function test_dashboard_status_update_shows_done_chip_in_conversation_center(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Status Chip Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $this->inboundOn($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $task = Task::find($session->taskIdList()[0]);
        $this->assertNull($task->completed_at);

        app(NativeTaskService::class)->updateFields($task, ['status' => 'done'], $boss->resolveEmployee());

        $task = $task->fresh(['currentState']);
        $this->assertNotNull($task->completed_at);
        $this->assertTrue($session->fresh()->isDone());

        $this->actingAs($boss);

        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->assertSee('Fix homepage crash')
            ->assertSeeHtml('px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide')
            ->assertSee('Done');
    }

    public function test_done_workflow_status_without_completed_at_still_marks_the_session_done(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Workflow Done Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $this->inboundOn($conversation, 'The homepage crashes on save');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $task = Task::find($session->taskIdList()[0]);
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        $task->update(['current_state_id' => $done->id, 'completed_at' => null]);
        $this->assertTrue($session->fresh()->isDone());

        $session->refresh();
        $session->update(['status' => ConversationSessionStatus::Open]);
        $task->updateQuietly(['current_state_id' => $done->id, 'completed_at' => null]);
        $this->assertFalse($session->fresh()->isDone());

        $this->actingAs($boss);

        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->assertSee('Fix homepage crash')
            ->assertSee('Done');

        $this->assertTrue($session->fresh()->isDone());
    }

    public function test_selected_messages_create_a_task_alongside_existing_session_tasks(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Manual Task Co']);
        Project::factory()->create(['customer_id' => $customer->id, 'name' => 'Website Rebuild', 'status' => 'active']);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->queueAiOutputs([$this->aiOutput('Fix homepage crash', 'Website Rebuild')]);
        $message = $this->inboundOn($conversation, 'The homepage crashes on save. Also rotate the SSL cert.');
        (new ProcessBufferedConversation($conversation->fresh(), $conversation->fresh()->last_message_at->toIso8601String()))->handle();

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $aiTaskIds = $session->taskIdList();
        $this->assertNotEmpty($aiTaskIds);

        $this->actingAs($boss);

        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->call('toggleMessageSelection', $message->id)
            ->assertSee('1 selected')
            ->call('openApproveModalFromSelection')
            ->assertSet('showApproveModal', true)
            ->assertSet('creatingFromSelection', true)
            ->set('approveTitle', 'Rotate SSL certificate')
            ->call('approveAndCreateTask')
            ->assertSee('Rotate SSL certificate');

        $session->refresh();
        $taskIds = $session->taskIdList();
        $this->assertCount(count($aiTaskIds) + 1, $taskIds);

        $manualId = collect($taskIds)->first(fn (int $id) => ! in_array($id, $aiTaskIds, true));
        $manual = Task::find($manualId);
        $this->assertSame('Rotate SSL certificate', $manual->title);
        $this->assertSame('conversation_messages', $manual->metadata['source']);
        $this->assertContains($message->id, $manual->metadata['source_message_ids']);
    }

    public function test_create_task_modal_lists_pivot_linked_and_unassigned_projects(): void
    {
        $boss = User::where('email', env('ADMIN_EMAIL', 'admin@thespace.app'))->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Pivot Project Co']);
        $linked = Project::factory()->create([
            'customer_id' => null,
            'name' => 'Pivot Linked Build',
            'status' => 'active',
        ]);
        $linked->customers()->attach($customer->id);
        Project::factory()->create([
            'customer_id' => null,
            'name' => 'Internal Unassigned Build',
            'status' => 'active',
        ]);

        [$conversation, $session] = $this->inboundSession($customer, 'Please fix the homepage');
        $session->update(['project_id' => $linked->id]);

        $this->actingAs($boss);

        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->call('openApproveModal')
            ->assertSet('showApproveModal', true)
            ->assertSet('approveProjectId', $linked->id)
            ->assertSee('Pivot Linked Build')
            ->assertSee('Internal Unassigned Build');
    }

    public function test_rail_shows_unread_inbox_and_overdue_task_badges(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Badge Customer']);
        $this->inboundSession($customer, 'Waiting on you');

        Task::factory()->create([
            'title' => 'Past due rail badge task',
            'due_date' => now()->subDay(),
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="1 unread"', false)
            ->assertSee('aria-label="1 overdue"', false);
    }

    public function test_employee_my_tasks_rail_shows_assigned_overdue_count(): void
    {
        $employeeUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $employee = $employeeUser->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Ahmed overdue rail task',
            'project_id' => $project->id,
            'due_date' => now()->subDay(),
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $this->actingAs($employeeUser)
            ->get(route('workspace.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="1 overdue"', false)
            ->assertDontSee('aria-label="1 unread"', false);
    }

    public function test_empty_inbox_offers_a_next_action(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get(route('conversation-center'))
            ->assertOk()
            ->assertSee('No sessions yet')
            ->assertSee('Start a thread with a customer.')
            ->assertSee('Start a thread');

        $this->get(route('conversation-center', ['tab' => 'unread']))
            ->assertOk()
            ->assertSee('Nothing unread')
            ->assertSee('Open All to see every session, or start a new one.')
            ->assertSee('View all');

        $this->get(route('conversation-center', ['create' => 1]))
            ->assertOk()
            ->assertSee('Open a new thread with an existing customer.');
    }

    public function test_inbox_browse_shows_customer_then_sessions_then_chat(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $alpha = Customer::factory()->create(['name' => 'Alpha Browse Co', 'phone' => '15551110001']);
        $beta = Customer::factory()->create(['name' => 'Beta Browse Co', 'phone' => '15551110002']);

        [$alphaConversation, $alphaSession] = $this->inboundSession($alpha, 'Alpha needs a fix');
        $this->inboundSession($beta, 'Beta needs a quote');
        $alphaSession->update(['title' => 'Alpha website fix']);

        $this->actingAs($boss);

        $browse = $this->get(route('conversation-center'))->assertOk();
        $browse->assertSee('Select a customer')
            ->assertSee('Alpha Browse Co')
            ->assertSee('Beta Browse Co')
            ->assertDontSee('Alpha website fix')
            ->assertDontSee('AI Thread Copilot')
            ->assertDontSee('Type WhatsApp reply...');

        Livewire::test(InboxPanel::class)
            ->assertSee('Alpha Browse Co')
            ->assertSeeHtml('text-sm font-semibold');

        $this->get(route('conversation-center', ['customer' => $alpha->id]))
            ->assertOk()
            ->assertSee('Alpha Browse Co')
            ->assertSee('Alpha website fix')
            ->assertSee('In progress')
            ->assertDontSee('AI Thread Copilot')
            ->assertDontSee('Type WhatsApp reply...');

        $this->get(route('conversation-center', [
            'customer' => $alpha->id,
            'conversation' => $alphaConversation->id,
            'session' => $alphaSession->id,
        ]))
            ->assertOk()
            ->assertSee('Alpha needs a fix')
            ->assertSee('AI Thread Copilot')
            ->assertSee('Customer Profile')
            ->assertDontSee('Select a customer');
    }

    public function test_deleting_a_conversation_refreshes_the_inbox_sidebar(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Sidebar Delete Co']);
        [$conversation] = $this->inboundSession($customer, 'Please delete me');

        $this->actingAs($boss);

        $sidebar = Livewire::test(InboxPanel::class);
        $sidebar->assertSee('Sidebar Delete Co');

        Livewire::test(ConversationCenter::class)
            ->call('deleteConversation', $conversation->id)
            ->assertDispatched(AppShell::UPDATED_EVENT);

        $sidebar->assertSee('Sidebar Delete Co');

        $sidebar->dispatch(AppShell::UPDATED_EVENT)
            ->assertDontSee('Sidebar Delete Co');
    }

    public function test_opening_a_thread_clears_the_unread_rail_badge_without_a_reply(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Read Me Harbor Co']);
        [$conversation, $session] = $this->inboundSession($customer, 'Please look at this');

        $this->assertTrue($session->fresh()->load('latestMessage')->isUnread());
        $this->assertSame(1, ConversationSession::query()->unread()->count());

        $this->actingAs($boss);

        Livewire::test(RailBadge::class, ['kind' => 'inbox', 'placement' => 'rail'])
            ->assertSee('aria-label="1 unread"', false);

        Livewire::test(ConversationCenter::class)
            ->call('selectConversation', $conversation->id);

        $session->refresh();
        $this->assertNotNull($session->last_read_at);
        $this->assertFalse($session->fresh()->load('latestMessage')->isUnread());
        $this->assertSame(0, ConversationSession::query()->unread()->count());

        Livewire::test(RailBadge::class, ['kind' => 'inbox', 'placement' => 'rail'])
            ->assertDontSee('aria-label="1 unread"', false);
    }

    public function test_already_read_inbound_thread_is_not_unread(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Already Read Co']);
        [$conversation, $session] = $this->inboundSession($customer, 'Already seen');
        $session->update(['last_read_at' => now()->addMinute()]);
        $conversation->update(['last_read_at' => now()->addMinute()]);

        $this->actingAs($boss);

        Livewire::test(InboxPanel::class)
            ->set('tab', 'unread')
            ->assertDontSee('Already Read Co')
            ->assertSee('Nothing unread');

        $this->get(route('conversation-center', ['tab' => 'unread']))
            ->assertOk()
            ->assertSee('Nothing unread');

        $this->assertSame(0, ConversationSession::query()->unread()->count());
    }

    public function test_inbound_message_creates_a_navbar_notification(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Notify Harbor Co']);
        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'sender_identifier' => '15550000006',
            'sender_name' => $customer->name,
            'body' => 'Can you check the invoice',
            'customer_id' => $customer->id,
            'status' => 'received',
        ]);

        $this->actingAs($boss);

        Livewire::test(Notifications::class)
            ->assertSee('Notify Harbor Co')
            ->assertSee('Can you check the invoice');

        $this->assertDatabaseHas('notifications', [
            'type' => 'conversation',
            'title' => 'Notify Harbor Co',
            'body' => 'Can you check the invoice',
        ]);
    }

    /**
     * @return array{0: Conversation, 1: ConversationSession}
     */
    protected function inboundSession(Customer $customer, string $body): array
    {
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');
        $message = $this->inboundOn($conversation, $body);

        return [$conversation->fresh(), $message->session->fresh(['latestMessage'])];
    }

    protected function inboundOn(Conversation $conversation, string $body): Message
    {
        return app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'sender_identifier' => '15550000001',
            'sender_name' => $conversation->customer?->name,
            'body' => $body,
            'customer_id' => $conversation->customer_id,
            'status' => 'received',
            'processing_status' => ['ai_analyzed' => false, 'media_downloaded' => true],
        ]);
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
