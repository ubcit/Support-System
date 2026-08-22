<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Customers\Models\Customer;
use Modules\Issues\Models\Issue;
use Tests\TestCase;

/**
 * Exercises the unknown WhatsApp number verification branch of
 * Modules\Communication\Jobs\ProcessIncomingMessage::processWhatsApp().
 */
class EndToEndBusinessScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_message_from_unknown_number_holds_for_project_code(): void
    {
        Queue::fake([SendOutboundMessage::class]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['profile' => ['name' => 'New Contact']],
                                ],
                                'messages' => [
                                    [
                                        'from' => '15551234567',
                                        'id' => 'wamid.TEST123',
                                        'text' => ['body' => 'The server in branch 4 is down, please fix ASAP.'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload);
        $appSecret = config('services.whatsapp.app_secret');
        $headers = [];
        if ($appSecret) {
            $headers['X-Hub-Signature-256'] = 'sha256='.hash_hmac('sha256', $body, $appSecret);
        }

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload, $headers);

        $response->assertOk()->assertJson(['status' => 'success']);

        $customer = Customer::where('whatsapp_id', '15551234567')->first();
        $this->assertNotNull($customer, 'Customer should be auto-created for an unknown WhatsApp number.');
        $this->assertStringContainsString('Unknown', $customer->name);
        $this->assertTrue((bool) ($customer->metadata['needs_project_verification'] ?? false));

        $conversation = Conversation::where('customer_id', $customer->id)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('whatsapp', $conversation->channel);

        $message = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->first();
        $this->assertNotNull($message);
        $this->assertSame('The server in branch 4 is down, please fix ASAP.', $message->body);

        $session = ConversationSession::query()->where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($session);
        $this->assertSame(ConversationSessionStatus::AwaitingVerification, $session->status);

        $this->assertNull(
            Issue::where('conversation_id', $conversation->id)->first(),
            'Unknown numbers should not create a draft issue; they wait for a project code.'
        );

        Queue::assertPushed(SendOutboundMessage::class);
    }

    public function test_whatsapp_get_verification_challenge_echoes_hub_challenge(): void
    {
        config(['services.whatsapp.verify_token' => 'test-verify-token']);

        $response = $this->get('/api/v1/webhooks/whatsapp?hub_verify_token=test-verify-token&hub_challenge=echo-me-123');

        $response->assertOk();
        $this->assertSame('echo-me-123', $response->getContent());
    }

    public function test_whatsapp_get_verification_rejects_wrong_token(): void
    {
        config(['services.whatsapp.verify_token' => 'test-verify-token']);

        $response = $this->get('/api/v1/webhooks/whatsapp?hub_verify_token=wrong-token&hub_challenge=echo-me-123');

        $response->assertStatus(403);
    }
}
