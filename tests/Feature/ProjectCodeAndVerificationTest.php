<?php

namespace Tests\Feature;

use App\Mail\SessionReadyMail;
use App\Models\AiRequestLog;
use App\Services\AI\AIManager;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Projects\Support\ProjectCodeGenerator;
use Tests\TestCase;

class ProjectCodeAndVerificationTest extends TestCase
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

    public function test_project_create_generates_unique_code_and_attaches_owner_on_pivot(): void
    {
        $customer = Customer::factory()->create();
        $project = app(ProjectService::class)->create([
            'name' => 'Alpha Build',
            'customer_id' => $customer->id,
            'status' => 'active',
            'uuid' => (string) Str::uuid(),
        ]);

        $this->assertNotEmpty($project->code);
        $this->assertSame(6, strlen($project->code));
        $this->assertTrue($project->customers()->where('customers.id', $customer->id)->exists());

        $second = app(ProjectService::class)->create([
            'name' => 'Beta Build',
            'customer_id' => $customer->id,
            'status' => 'active',
            'uuid' => (string) Str::uuid(),
        ]);

        $this->assertNotSame($project->code, $second->code);
    }

    public function test_project_can_be_created_without_customer_and_attached_later(): void
    {
        $project = app(ProjectService::class)->create([
            'name' => 'Code Only Build',
            'status' => 'active',
            'uuid' => (string) Str::uuid(),
        ]);

        $this->assertNull($project->customer_id);
        $this->assertNotEmpty($project->code);
        $this->assertSame(6, strlen($project->code));
        $this->assertSame(0, $project->customers()->count());

        $customer = Customer::factory()->create();
        app(ProjectService::class)->attachCustomer($project, $customer);

        $this->assertTrue($project->fresh()->customers()->where('customers.id', $customer->id)->exists());
        $this->assertNull($project->fresh()->customer_id);
    }

    public function test_unknown_number_is_prompted_for_project_code_without_creating_issue(): void
    {
        Queue::fake([SendOutboundMessage::class]);

        $job = new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15559876543', 'New Contact', 'Please fix the server'),
            'whatsapp'
        );
        $job->handle(app(CustomerService::class), app(ConversationService::class));

        $customer = Customer::where('phone', '15559876543')->firstOrFail();
        $session = ConversationSession::query()->latest('id')->firstOrFail();

        $this->assertTrue((bool) ($customer->metadata['needs_project_verification'] ?? false));
        $this->assertSame(ConversationSessionStatus::AwaitingVerification, $session->status);
        $this->assertNull(Issue::query()->first());
        Queue::assertPushed(SendOutboundMessage::class);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->where('metadata->trigger', 'project_verification_prompt')
                ->where('body', 'Please verify yourself by entering your project code.')
                ->exists()
        );
        $this->assertSame('en', $session->fresh()->customer_locale);
        $this->assertSame('en', $customer->fresh()->preferred_locale);
    }

    public function test_arabic_first_message_gets_arabic_verification_prompt(): void
    {
        Queue::fake([SendOutboundMessage::class]);

        $job = new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15559870001', 'عميل جديد', 'الرجاء إصلاح الخادم'),
            'whatsapp'
        );
        $job->handle(app(CustomerService::class), app(ConversationService::class));

        $customer = Customer::where('phone', '15559870001')->firstOrFail();
        $session = ConversationSession::query()->latest('id')->firstOrFail();

        $this->assertSame('ar', $session->customer_locale);
        $this->assertSame('ar', $customer->preferred_locale);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->where('metadata->trigger', 'project_verification_prompt')
                ->where('body', 'يرجى التحقق من هويتك بإدخال رمز المشروع.')
                ->exists()
        );
    }

    public function test_valid_project_code_resumes_prior_messages_into_buffer(): void
    {
        Queue::fake([SendOutboundMessage::class, ProcessBufferedConversation::class]);

        $owner = Customer::factory()->create(['name' => 'Owner Co']);
        $project = Project::factory()->create([
            'customer_id' => $owner->id,
            'name' => 'Website Rebuild',
            'code' => 'AB12CD',
            'status' => 'active',
        ]);

        $service = app(CustomerService::class);
        $conversations = app(ConversationService::class);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15551112222', 'Guest', 'Homepage is down'),
            'whatsapp'
        ))->handle($service, $conversations);

        Queue::assertPushed(SendOutboundMessage::class);
        Queue::assertNotPushed(ProcessBufferedConversation::class);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15551112222', 'Guest', 'AB12CD'),
            'whatsapp'
        ))->handle($service, $conversations);

        $guest = Customer::where('phone', '15551112222')->firstOrFail();
        $session = ConversationSession::query()->latest('id')->firstOrFail();

        $this->assertTrue((bool) ($guest->metadata['project_verified'] ?? false));
        $this->assertFalse((bool) ($guest->metadata['needs_project_verification'] ?? true));
        $this->assertTrue($project->fresh()->customers()->where('customers.id', $guest->id)->exists());
        $this->assertSame($project->id, $session->project_id);
        $this->assertSame(ConversationSessionStatus::Collecting, $session->status);
        Queue::assertPushed(ProcessBufferedConversation::class);

        $requestMessage = Message::query()
            ->where('body', 'Homepage is down')
            ->firstOrFail();
        $this->assertFalse((bool) ($requestMessage->processing_status['ai_analyzed'] ?? true));
    }

    public function test_invalid_project_code_keeps_session_awaiting_verification(): void
    {
        Queue::fake([SendOutboundMessage::class, ProcessBufferedConversation::class]);

        Project::factory()->create(['code' => 'VALID1', 'status' => 'active']);

        $service = app(CustomerService::class);
        $conversations = app(ConversationService::class);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15553334444', 'Guest', 'Help me'),
            'whatsapp'
        ))->handle($service, $conversations);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15553334444', 'Guest', 'ZZZZZZ'),
            'whatsapp'
        ))->handle($service, $conversations);

        $session = ConversationSession::query()->latest('id')->firstOrFail();
        $this->assertSame(ConversationSessionStatus::AwaitingVerification, $session->status);
        Queue::assertNotPushed(ProcessBufferedConversation::class);
        $this->assertTrue(
            Message::query()
                ->where('metadata->trigger', 'project_verification_invalid')
                ->exists()
        );
    }

    public function test_code_only_first_message_awaits_real_request(): void
    {
        Queue::fake([SendOutboundMessage::class, ProcessBufferedConversation::class]);

        $project = Project::factory()->create(['code' => 'ONLY01', 'status' => 'active']);

        (new ProcessIncomingMessage(
            WhatsAppInboundPayload::text('15554445555', 'Guest', 'ONLY01'),
            'whatsapp'
        ))->handle(app(CustomerService::class), app(ConversationService::class));

        $session = ConversationSession::query()->latest('id')->firstOrFail();
        $customer = Customer::where('phone', '15554445555')->firstOrFail();

        $this->assertTrue((bool) ($customer->metadata['project_verified'] ?? false));
        $this->assertSame($project->id, $session->project_id);
        $this->assertSame(ConversationSessionStatus::Collecting, $session->status);
        Queue::assertNotPushed(ProcessBufferedConversation::class);
        $this->assertTrue(
            Message::query()
                ->where('metadata->trigger', 'project_verification_ok_awaiting_request')
                ->where('body', 'like', 'Verified for project%')
                ->exists()
        );
        $this->assertNull($session->fresh()->customer_locale);
    }

    public function test_pivot_customer_can_match_project(): void
    {
        $owner = Customer::factory()->create();
        $extra = Customer::factory()->create();
        $project = Project::factory()->create([
            'customer_id' => $owner->id,
            'name' => 'Shared Workspace',
            'status' => 'active',
        ]);

        app(ProjectService::class)->attachCustomer($project, $extra);

        $matched = app(ProjectService::class)->matchForCustomer($extra->id, 'Shared Workspace');
        $this->assertSame($project->id, $matched?->id);

        $byCode = app(ProjectService::class)->matchForCustomer($extra->id, $project->code);
        $this->assertSame($project->id, $byCode?->id);
    }

    public function test_session_complete_notifies_customer_and_project_staff(): void
    {
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Yousif Co',
            'phone' => '15551230000',
            'whatsapp_id' => '15551230000',
        ]);
        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Website Rebuild',
            'status' => 'active',
        ]);

        $staff = Employee::factory()->create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'phone' => '15559998877',
        ]);
        $project->employees()->attach($staff->id, ['role' => 'member', 'assigned_at' => now()]);

        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () {
                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => [
                        'is_actionable' => true,
                        'intent' => 'request',
                        'confidence' => 0.95,
                        'summary' => 'Fix homepage',
                        'project' => 'Website Rebuild',
                        'tasks' => [[
                            'title' => 'Fix homepage',
                            'description' => 'Crash on save',
                            'priority' => 'high',
                            'assigned_to' => '',
                        ]],
                    ],
                    'provider' => 'mock',
                    'model_name' => 'test',
                ]);
            });
        });

        app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'sender_identifier' => '15551230000',
            'sender_name' => 'Yousif Co',
            'body' => 'Homepage crashes',
            'customer_id' => $customer->id,
            'status' => 'received',
            'processing_status' => ['ai_analyzed' => false, 'media_downloaded' => true],
        ]);

        (new ProcessBufferedConversation(
            $conversation->fresh(),
            $conversation->fresh()->last_message_at->toIso8601String()
        ))->handle();

        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->where('metadata->trigger', 'session_processing')
                ->where('body', 'We received your request and it is now being processed.')
                ->exists()
        );

        Mail::assertSent(SessionReadyMail::class, function (SessionReadyMail $mail) use ($staff) {
            return $mail->hasTo($staff->email);
        });

        Http::assertSent(function ($request) use ($staff) {
            return str_contains($request->url(), 'graph.facebook.com')
                && ($request['to'] ?? null) === $staff->phone;
        });
    }

    public function test_boss_sessions_skip_session_ready_notifications(): void
    {
        Mail::fake();
        Http::fake();

        $bossPhone = '+9647700000000';
        $customer = Customer::factory()->create([
            'phone' => $bossPhone,
            'whatsapp_id' => $bossPhone,
        ]);
        $conversation = app(ConversationService::class)->findOrCreateActive($customer, 'whatsapp');

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () {
                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => [
                        'is_actionable' => true,
                        'intent' => 'command',
                        'confidence' => 0.95,
                        'summary' => 'Boss command',
                        'project' => '',
                        'tasks' => [[
                            'title' => 'Boss task',
                            'description' => 'Do it',
                            'priority' => 'high',
                            'assigned_to' => '',
                        ]],
                    ],
                    'provider' => 'mock',
                    'model_name' => 'test',
                ]);
            });
        });

        app(ConversationService::class)->addMessage($conversation, [
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'sender_identifier' => $bossPhone,
            'sender_name' => 'Boss',
            'body' => 'Create a task',
            'customer_id' => $customer->id,
            'status' => 'received',
            'processing_status' => ['ai_analyzed' => false, 'media_downloaded' => true],
            'metadata' => ['is_boss' => true],
        ]);

        (new ProcessBufferedConversation(
            $conversation->fresh(),
            $conversation->fresh()->last_message_at->toIso8601String(),
            0,
            true,
        ))->handle();

        Mail::assertNothingOutgoing();
        $this->assertFalse(
            Message::query()
                ->where('metadata->trigger', 'session_processing')
                ->exists()
        );
    }

    public function test_code_generator_normalizes_input(): void
    {
        $codes = app(ProjectCodeGenerator::class);
        $this->assertSame('AB12CD', $codes->normalize(' ab-12 cd '));
    }
}
