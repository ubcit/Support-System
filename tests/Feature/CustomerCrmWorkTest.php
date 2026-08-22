<?php

namespace Tests\Feature;

use App\Livewire\CustomerCRM\Index as CustomerCrm;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Customers\Models\Customer;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Tests\TestCase;

class CustomerCrmWorkTest extends TestCase
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

    public function test_crm_lists_project_metadata_session_and_conversation_work(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $customer = Customer::factory()->create([
            'name' => 'Harbor CRM Co',
            'company' => 'Harbor Industries',
        ]);
        $stranger = Customer::factory()->create(['name' => 'Stranger Tools']);

        $project = Project::factory()->create([
            'customer_id' => $customer->id,
            'name' => 'Harbor Website',
        ]);
        Project::factory()->create([
            'customer_id' => $stranger->id,
            'name' => 'Stranger Website',
        ]);

        $projectTask = Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Harbor project paint job',
        ]);

        $metadataTask = Task::factory()->create([
            'title' => 'Harbor WhatsApp follow-up',
            'metadata' => ['customer_id' => $customer->id, 'source' => 'customer_message'],
        ]);

        $sessionTask = Task::factory()->create([
            'title' => 'Harbor session-only task',
        ]);

        $strangerTask = Task::factory()->create([
            'title' => 'Stranger only task',
            'metadata' => ['customer_id' => $stranger->id],
        ]);

        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'active',
        ]);

        ConversationSession::create([
            'conversation_id' => $conversation->id,
            'status' => ConversationSessionStatus::Open,
            'title' => 'Harbor intake',
            'task_ids' => [$sessionTask->id],
        ]);

        $conversationIssue = Issue::factory()->create([
            'title' => 'Harbor conv-only leak',
            'description' => 'Reported on WhatsApp only.',
            'conversation_id' => $conversation->id,
            'customer_id' => null,
            'status' => 'open',
        ]);

        $strangerIssue = Issue::factory()->create([
            'title' => 'Stranger only leak',
            'description' => 'Should stay off Harbor CRM.',
            'customer_id' => $stranger->id,
            'status' => 'open',
        ]);

        $this->actingAs($boss);

        Livewire::test(CustomerCrm::class)
            ->call('selectCustomer', $customer->id)
            ->assertSee('Harbor project paint job')
            ->assertSee('Harbor WhatsApp follow-up')
            ->assertSee('Harbor session-only task')
            ->assertSee('Harbor conv-only leak')
            ->assertSee('Harbor Website')
            ->assertSee('Harbor intake')
            ->assertDontSee($strangerTask->title)
            ->assertDontSee($strangerIssue->title)
            ->assertDontSee('Stranger Website');

        $this->assertTrue(
            Customer::linkedTaskQuery($customer->id)->pluck('id')->contains($projectTask->id)
        );
        $this->assertTrue(
            Customer::linkedTaskQuery($customer->id)->pluck('id')->contains($metadataTask->id)
        );
        $this->assertTrue(
            Customer::linkedIssueQuery($customer->id)->pluck('id')->contains($conversationIssue->id)
        );
        $this->assertFalse(
            Customer::linkedTaskQuery($customer->id)->pluck('id')->contains($strangerTask->id)
        );
    }
}
