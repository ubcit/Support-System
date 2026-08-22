<?php

namespace Tests\Feature;

use App\Helpers\TaskNav;
use App\Jobs\SendNotificationEmailJob;
use App\Livewire\ConversationCenter\Index as ConversationCenter;
use App\Livewire\Header\Notifications as HeaderNotifications;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use App\Livewire\TaskDashboard\Index as TaskDashboard;
use App\Livewire\TaskDetail\Index as TaskDetail;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Customers\Models\Customer;
use Modules\Notifications\Models\Notification;
use Modules\Tasks\Models\CommentMention;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskStakeholder;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskWorkflowImprovementsTest extends TestCase
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

    public function test_employee_dashboard_defaults_to_mine_and_hides_other_open_tasks(): void
    {
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $actor = $ahmed->resolveEmployee();
        $other = User::where('email', 'sara@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $mine = Task::factory()->create([
            'title' => 'Ahmed only open task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $mine->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $team = Task::factory()->create([
            'title' => 'Sara only open task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $team->assignments()->create(['employee_id' => $other->id, 'assigned_at' => now()]);

        $this->actingAs($ahmed)
            ->get(route('workspace.employee'))
            ->assertOk()
            ->assertSee('Ahmed only open task')
            ->assertDontSee('Sara only open task')
            ->assertDontSee('Review Queue')
            ->assertDontSee('queue=review', false);
    }

    public function test_manager_dashboard_shows_review_queue_and_all_open_tasks(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $other = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $team = Task::factory()->create([
            'title' => 'Ahmed team open task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $team->assignments()->create(['employee_id' => $other->id, 'assigned_at' => now()]);

        $this->actingAs($boss)
            ->get(route('task-dashboard'))
            ->assertOk()
            ->assertSee('Ahmed team open task')
            ->assertSee('Review Queue')
            ->assertSee('Recently Done')
            ->assertSee('queue=review', false);
    }

    public function test_employee_cannot_complete_a_review_task_until_a_reviewer_approves(): void
    {
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $actor = $ahmed->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $service = app(NativeTaskService::class);

        $task = Task::factory()->create([
            'title' => 'Needs review before done',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $blocked = $service->moveToState($task->fresh(), $done->id, $actor);
        $this->assertSame($review->id, (int) $blocked->current_state_id);
        $this->assertNull($blocked->completed_at);

        $this->assertTrue($task->fresh()->mustPassReview());

        $this->actingAs($ahmed);
        Livewire::test(TaskDashboard::class)
            ->assertDontSee("moveTaskToState({$task->id}, {$done->id})", false);

        $this->assertNull($service->approveTask($task->fresh(), $actor, 'Self approve'));

        $boss = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $approved = $service->approveTask($task->fresh(), $boss, 'Looks good');

        $this->assertNotNull($approved);
        $this->assertSame($done->id, (int) $task->fresh()->current_state_id);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_kanban_move_to_done_is_blocked_without_approval(): void
    {
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Kanban review task',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);

        $result = app(\Modules\Tasks\Services\KanbanEngineService::class)->moveCard($task, $done->id, 0, $ahmed);

        $this->assertFalse($result['success']);
        $this->assertSame($review->id, (int) $task->fresh()->current_state_id);
    }

    public function test_manager_can_complete_a_review_task_without_prior_approval(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Manager bypass review',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);

        TaskStakeholder::create([
            'task_id' => $task->id,
            'employee_id' => $actor->id,
            'role' => 'reviewer',
            'assigned_by' => $actor->id,
        ]);

        $completed = app(NativeTaskService::class)->moveToState($task->fresh(), $done->id, $actor);

        $this->assertSame($done->id, (int) $completed->current_state_id);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_moving_a_task_to_review_notifies_managers(): void
    {
        Queue::fake();

        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Ready for the boss',
            'current_state_id' => $inProgress->id,
            'workflow_id' => $inProgress->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);

        app(NativeTaskService::class)->moveToState($task->fresh(), $review->id, $ahmed);

        $this->assertTrue(
            Notification::query()
                ->where('employee_id', $boss->id)
                ->where('type', 'review_requested')
                ->exists()
        );
        $this->assertFalse(
            Notification::query()
                ->where('employee_id', $ahmed->id)
                ->where('type', 'review_requested')
                ->exists()
        );
        Queue::assertPushed(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $boss, $ahmed) {
            return $job->type === 'review_requested'
                && $job->modelId === $task->id
                && $job->employeeId === $boss->id
                && $job->actorId === $ahmed->id;
        });

        $this->actingAs(User::where('email', 'boss@thespace.app')->firstOrFail());
        Livewire::test(HeaderNotifications::class)
            ->assertSee('Ready for review: Ready for the boss')
            ->assertSee(TaskNav::detailUrl($task->id, []), false);
    }

    public function test_employee_cannot_complete_whatsapp_session_work_without_review(): void
    {
        $ahmedUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $ahmed = $ahmedUser->resolveEmployee();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $service = app(NativeTaskService::class);

        $task = Task::factory()->create([
            'title' => 'Customer WhatsApp work',
            'current_state_id' => $inProgress->id,
            'workflow_id' => $inProgress->workflow_id,
            'metadata' => ['conversation_session_id' => 99, 'source' => 'customer_message'],
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);

        $this->assertTrue($task->fresh()->mustPassReview());

        $blocked = $service->moveToState($task->fresh(), $done->id, $ahmed);
        $this->assertSame($inProgress->id, (int) $blocked->current_state_id);
        $this->assertNull($blocked->completed_at);

        $this->actingAs($ahmedUser);
        Livewire::test(\App\Livewire\Workspace\EmployeeWorkspace\Index::class)
            ->set('selectedTaskId', $task->id)
            ->assertSee("updateStatus({$task->id}, 'code_review')", false)
            ->assertDontSee("updateStatus({$task->id}, 'done')", false);

        $moved = $service->moveToState($task->fresh(), $review->id, $ahmed);
        $this->assertSame($review->id, (int) $moved->current_state_id);

        $boss = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $service->approveTask($task->fresh(), $boss, 'Ship it');
        $this->assertSame($done->id, (int) $task->fresh()->current_state_id);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_comment_mention_creates_in_app_notification_and_queues_email(): void
    {
        Queue::fake();

        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $sara = User::where('email', 'sara@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Mentionable task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);
        $task->assignments()->create(['employee_id' => $sara->id, 'assigned_at' => now()]);

        $comment = app(NativeTaskService::class)->addComment(
            $task,
            'Can you check this @[Sara]?',
            $ahmed,
        );

        $this->assertTrue(
            CommentMention::query()
                ->where('task_comment_id', $comment->id)
                ->where('employee_id', $sara->id)
                ->exists()
        );

        $this->assertTrue(
            Notification::query()
                ->where('employee_id', $sara->id)
                ->where('type', 'comment_mention')
                ->exists()
        );

        Queue::assertPushed(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($comment, $sara) {
            return $job->type === 'comment_mention'
                && $job->modelId === $comment->id
                && $job->employeeId === $sara->id;
        });
    }

    public function test_completing_all_session_tasks_sends_whatsapp_auto_reply(): void
    {
        Queue::fake();

        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $customer = Customer::factory()->create([
            'name' => 'Harbor Co',
            'phone' => '15551234567',
            'whatsapp_id' => '15551234567',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $task = Task::factory()->create([
            'title' => 'Session task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $session = ConversationSession::create([
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Open,
            'task_ids' => [$task->id],
            'title' => 'Harbor request',
        ]);

        $task->update([
            'current_state_id' => $done->id,
            'completed_at' => now(),
            'metadata' => ['conversation_session_id' => $session->id],
        ]);

        $session->refresh();
        $this->assertSame(ConversationSessionStatus::Done, $session->status);

        $this->assertTrue(
            Message::query()
                ->where('conversation_session_id', $session->id)
                ->where('direction', 'outbound')
                ->where('body', 'like', '%completed and is ready%')
                ->exists()
        );

        Queue::assertPushed(SendOutboundMessage::class);
    }

    public function test_completing_session_sends_arabic_auto_reply_when_session_locale_is_ar(): void
    {
        Queue::fake();

        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $customer = Customer::factory()->create([
            'name' => 'عميل معروف',
            'phone' => '15551234999',
            'whatsapp_id' => '15551234999',
            'preferred_locale' => 'ar',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $task = Task::factory()->create([
            'title' => 'Arabic session task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $session = ConversationSession::create([
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Open,
            'customer_locale' => 'ar',
            'task_ids' => [$task->id],
            'title' => 'طلب عربي',
        ]);

        $task->update([
            'current_state_id' => $done->id,
            'completed_at' => now(),
            'metadata' => ['conversation_session_id' => $session->id],
        ]);

        $session->refresh();
        $this->assertSame(ConversationSessionStatus::Done, $session->status);

        $this->assertTrue(
            Message::query()
                ->where('conversation_session_id', $session->id)
                ->where('direction', 'outbound')
                ->where('body', 'like', '%طلبك قد اكتمل%')
                ->exists()
        );

        Queue::assertPushed(SendOutboundMessage::class);
    }

    public function test_manager_can_approve_a_review_task_from_the_inbox(): void
    {
        Queue::fake();

        $workspace = \Modules\MultiTenancy\Models\Workspace::firstOrFail();
        $bossUser = User::where('email', 'boss@thespace.app')->firstOrFail();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $customer = Customer::factory()->create([
            'name' => 'Inbox Review Co',
            'phone' => '15551112222',
            'whatsapp_id' => '15551112222',
            'workspace_id' => $workspace->id,
        ]);

        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $task = Task::factory()->create([
            'title' => 'Inbox review task',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
            'workspace_id' => $workspace->id,
        ]);

        $session = ConversationSession::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Open,
            'task_ids' => [$task->id],
            'title' => 'Inbox review request',
            'last_message_at' => now(),
        ]);
        $task->update(['metadata' => ['conversation_session_id' => $session->id]]);

        $this->actingAs(User::where('email', 'ahmed@thespace.app')->firstOrFail());
        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->assertSee('Inbox review task')
            ->assertDontSee('Approve &amp; done', false);

        $this->actingAs($bossUser);
        Livewire::test(ConversationCenter::class)
            ->set('selectedConversationId', $conversation->id)
            ->set('selectedSessionId', $session->id)
            ->assertSee('Approve &amp; done', false)
            ->call('approveSessionTask', $task->id);

        $this->assertSame($done->id, (int) $task->fresh()->current_state_id);
        $this->assertTrue($session->fresh()->isDone());
        Queue::assertPushed(SendOutboundMessage::class);
    }

    public function test_session_completion_skips_auto_reply_when_workspace_setting_is_disabled(): void
    {
        Queue::fake();

        $workspace = \Modules\MultiTenancy\Models\Workspace::firstOrFail();
        $workspace->setSetting('auto_reply_on_completion', false);

        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $customer = Customer::factory()->create([
            'name' => 'Quiet Co',
            'phone' => '15557654321',
            'whatsapp_id' => '15557654321',
        ]);

        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $task = Task::factory()->create([
            'title' => 'Quiet session task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $session = ConversationSession::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Open,
            'task_ids' => [$task->id],
            'title' => 'Quiet request',
        ]);

        $task->update([
            'current_state_id' => $done->id,
            'completed_at' => now(),
            'metadata' => ['conversation_session_id' => $session->id],
        ]);

        $session->refresh();
        $this->assertSame(ConversationSessionStatus::Done, $session->status);
        $this->assertFalse(
            Message::query()
                ->where('conversation_session_id', $session->id)
                ->where('direction', 'outbound')
                ->exists()
        );
        Queue::assertNotPushed(SendOutboundMessage::class);
    }

    public function test_task_detail_renders_mention_composer_and_posts_a_comment(): void
    {
        Queue::fake();

        $ahmedUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $ahmed = $ahmedUser->resolveEmployee();
        $sara = User::where('email', 'sara@thespace.app')->firstOrFail()->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Detail mention task',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);
        $task->assignments()->create(['employee_id' => $sara->id, 'assigned_at' => now()]);

        $this->actingAs($ahmedUser)
            ->get(route('workspace.task-detail', $task->id))
            ->assertOk()
            ->assertSee('Use @ to mention someone')
            ->assertSee('Type @ to mention')
            ->assertSee('Review Required')
            ->assertSee('Waiting for manager review')
            ->assertDontSee('Approve &amp; complete', false)
            ->assertDontSee('Request Changes');

        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->assertOk()
            ->assertSee('Use @ to mention someone')
            ->assertSee('Review Required')
            ->set('newCommentText', 'Please look @[Sara]')
            ->call('addComment')
            ->assertSee('Please look')
            ->assertSee('@Sara');

        $this->actingAs(User::where('email', 'boss@thespace.app')->firstOrFail());
        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->assertSee('Approve &amp; complete', false)
            ->assertSee('Request Changes');

        $this->assertTrue(
            CommentMention::query()
                ->where('employee_id', $sara->id)
                ->exists()
        );
    }

    public function test_employee_workspace_cannot_complete_a_review_task(): void
    {
        $ahmedUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $ahmed = $ahmedUser->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Workspace review task',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);

        $this->actingAs($ahmedUser);

        Livewire::test(\App\Livewire\Workspace\EmployeeWorkspace\Index::class)
            ->call('updateStatus', $task->id, 'done')
            ->assertSee('Waiting for manager review');

        $this->assertSame($review->id, (int) $task->fresh()->current_state_id);
        $this->assertNull($task->fresh()->completed_at);

        Livewire::test(\App\Livewire\Workspace\Dashboard\Index::class)
            ->call('updateTaskStatus', $task->id, 'done');

        $this->assertSame($review->id, (int) $task->fresh()->current_state_id);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_employee_workspace_comment_mention_notifies_assignee(): void
    {
        Queue::fake();

        $ahmedUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $ahmed = $ahmedUser->resolveEmployee();
        $sara = User::where('email', 'sara@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Workspace mention task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);
        $task->assignments()->create(['employee_id' => $sara->id, 'assigned_at' => now()]);

        $this->actingAs($ahmedUser);

        Livewire::test(\App\Livewire\Workspace\EmployeeWorkspace\Index::class)
            ->set('selectedTaskId', $task->id)
            ->set('newComment', 'Need a look @[Sara]')
            ->call('addComment')
            ->assertSee('Need a look')
            ->assertSee('@Sara');

        $this->assertTrue(
            CommentMention::query()->where('employee_id', $sara->id)->exists()
        );
        $this->assertTrue(
            Notification::query()
                ->where('employee_id', $sara->id)
                ->where('type', 'comment_mention')
                ->exists()
        );
    }

    public function test_manager_can_approve_from_the_task_dashboard_review_queue(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Dashboard review queue task',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->set('filterReview', 'review')
            ->assertSee('Dashboard review queue task')
            ->assertSee('Approve')
            ->call('approveTask', $task->id);

        $this->assertTrue(
            TaskStakeholder::query()
                ->where('task_id', $task->id)
                ->where('approval_status', 'approved')
                ->exists()
        );

        $this->assertSame($done->id, (int) $task->fresh()->current_state_id);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_manager_can_request_changes_from_the_dashboard_with_a_note(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Dashboard changes note task',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->set('filterReview', 'review')
            ->call('openReviewModal', $task->id)
            ->assertSet('showReviewModal', true)
            ->assertSee('Dashboard changes note task')
            ->set('reviewNote', 'Please add screenshots')
            ->call('submitReview')
            ->assertSet('showReviewModal', false);

        $this->assertSame($inProgress->id, (int) $task->fresh()->current_state_id);
        $this->assertSame(
            'Please add screenshots',
            TaskStakeholder::query()->where('task_id', $task->id)->value('approval_note')
        );
    }

    public function test_manager_can_set_kanban_wip_limits(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('updateWipLimit', $todo->id, 4);

        $this->assertSame(4, (int) $todo->fresh()->wip_limit);

        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $this->actingAs($ahmed);

        Livewire::test(TaskDashboard::class)
            ->call('updateWipLimit', $todo->id, 9);

        $this->assertSame(4, (int) $todo->fresh()->wip_limit);
    }

    public function test_requesting_changes_returns_the_task_to_in_progress_and_notifies_the_assignee(): void
    {
        Queue::fake();

        $boss = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $ahmedUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $ahmed = $ahmedUser->resolveEmployee();
        $review = WorkflowState::where('name', 'Review')->firstOrFail();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Needs a rewrite',
            'current_state_id' => $review->id,
            'workflow_id' => $review->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmed->id, 'assigned_at' => now()]);

        app(NativeTaskService::class)->requestChanges($task, $boss, 'Please add screenshots');

        $this->assertSame($inProgress->id, (int) $task->fresh()->current_state_id);
        $this->assertTrue(
            TaskStakeholder::query()
                ->where('task_id', $task->id)
                ->where('approval_status', 'changes_requested')
                ->exists()
        );
        $this->assertTrue(
            Notification::query()
                ->where('employee_id', $ahmed->id)
                ->where('type', 'task_changes_requested')
                ->exists()
        );
        Queue::assertPushed(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $ahmed) {
            return $job->type === 'task_changes_requested'
                && $job->modelId === $task->id
                && $job->employeeId === $ahmed->id;
        });

        $this->actingAs($ahmedUser);
        Livewire::test(\App\Livewire\Workspace\EmployeeWorkspace\Index::class)
            ->set('selectedTaskId', $task->id)
            ->assertSee('Changes requested')
            ->assertSee('Please add screenshots')
            ->assertSee("updateStatus({$task->id}, 'code_review')", false)
            ->assertDontSee("updateStatus({$task->id}, 'done')", false);

        Livewire::test(TaskDashboard::class)
            ->assertDontSee("moveTaskToState({$task->id}, {$done->id})", false)
            ->call('openEditModal', $task->id)
            ->assertDontSee('<option value="done">Done</option>', false);

        Livewire::test(TaskDashboard::class)
            ->set('currentView', 'board')
            ->assertDontSee("moveTaskToState({$task->id}, {$done->id})", false);
    }
}
