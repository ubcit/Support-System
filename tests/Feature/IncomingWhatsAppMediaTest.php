<?php

namespace Tests\Feature;

use App\Models\AiRequestLog;
use App\Services\AI\AIManager;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Attachments\Enums\AttachmentType;
use Modules\Attachments\Models\Attachment;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Jobs\ProcessIncomingMessage;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Services\ConversationService;
use Modules\Communication\Support\WhatsAppInboundPayload;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerService;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Tests\TestCase;

class IncomingWhatsAppMediaTest extends TestCase
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

        $this->app['config']->set('services.gemini.key', 'test-gemini-key');
        $this->app['config']->set('services.gemini.media_model', 'gemini-3.5-flash-lite');
        $this->app['config']->set('services.openai.key', null);
        $this->app['config']->set('services.whatsapp.access_token', 'test-whatsapp-token');
    }

    public function test_known_customer_media_creates_attachment_and_extracts_when_applicable(): void
    {
        $cases = [
            // image/* -> kept as attachment by default (no AI analysis unless opt-in)
            ['image/jpeg', AttachmentType::Image, null, 'sample.jpg', false],
            // audio/* -> whatsapp type "audio" -> ProcessAttachment transcribes with Gemini
            ['audio/ogg', AttachmentType::Voice, 'gemini transcript', 'note.ogg', true],

            // video/* -> whatsapp type "video" -> download only (no OpenAI)
            ['video/mp4', AttachmentType::Video, null, 'video.mp4', false],

            // application/pdf -> whatsapp type "document" -> ProcessAttachment download only
            ['application/pdf', AttachmentType::Pdf, null, 'doc.pdf', false],

            // Office + text docs
            ['application/msword', AttachmentType::Document, null, 'doc.doc', false],
            ['text/plain', AttachmentType::Document, null, 'note.txt', false],

            // Spreadsheet + archives
            ['application/vnd.ms-excel', AttachmentType::Spreadsheet, null, 'sheet.xls', false],
            ['application/zip', AttachmentType::Archive, null, 'archive.zip', false],

            // Unknown -> attachment "other"
            ['application/octet-stream', AttachmentType::Other, null, 'blob.bin', false],
        ];

        Storage::fake('local');

        // AIManager is invoked by ProcessBufferedConversation (triggered after DownloadAttachment -> ProcessAttachment).
        // We keep a single mock for the entire matrix and assert based on "current" expected markers.
        $currentFilename = '';
        $currentExpectedTranscript = null;
        $currentShouldCallMediaAi = false;

        $this->mock(AIManager::class, function ($mock) use (
            &$currentFilename,
            &$currentExpectedTranscript,
            &$currentShouldCallMediaAi
        ) {
            $mock->shouldReceive('execute')
                ->andReturnUsing(function () use (&$currentFilename, &$currentExpectedTranscript, &$currentShouldCallMediaAi) {
                    $args = func_get_args();
                    $messageBody = $args[0] ?? '';
                    $context = $args[6] ?? [];

                    if ($currentShouldCallMediaAi && $currentExpectedTranscript !== null) {
                        $marker = '[voice transcript] '.$currentExpectedTranscript;
                        $this->assertStringContainsString($marker, $messageBody);
                    } else {
                        $this->assertStringNotContainsString('[attachment] '.$currentFilename, $messageBody);
                        if ($currentExpectedTranscript !== null) {
                            $this->assertStringNotContainsString($currentExpectedTranscript, $messageBody);
                        }
                    }

                    return AiRequestLog::create([
                        'uuid' => (string) Str::uuid(),
                        'validation_status' => 'passed',
                        'parsed_json' => [
                            'is_actionable' => false,
                            'intent' => 'media',
                            'confidence' => 0.0,
                            'summary' => 'No action',
                            'project' => '',
                            'tasks' => [],
                        ],
                        'provider' => 'mock',
                        'model_name' => 'test',
                        'cost' => 0,
                        'total_tokens' => 0,
                        'customer_id' => $context['customer_id'] ?? null,
                        'conversation_id' => $context['conversation_id'] ?? null,
                        'conversation_session_id' => $context['conversation_session_id'] ?? null,
                        'source' => $context['source'] ?? 'conversation',
                    ]);
                })
                ->zeroOrMoreTimes();
        });

        $basePhone = '15551234567';

        $mediaIdToMime = [];
        $mediaIdToBytes = [];
        foreach ($cases as $i => $case) {
            [$mimeType] = $case;
            $mediaId = 'media_'.$i;
            $mediaIdToMime[$mediaId] = $mimeType;
            $mediaIdToBytes[$mediaId] = 'test-bytes-for-'.$mimeType;
        }

        $graphMetaCallsById = [];
        $graphDownloadCallsById = [];
        $geminiMediaCalls = 0;

        // Single HTTP fake for the whole matrix.
        // (Re-faking inside the loop can lead to stale fakes being reused by the runner.)
        Http::fake(function ($request) use (
            $mediaIdToMime,
            $mediaIdToBytes,
            &$graphMetaCallsById,
            &$graphDownloadCallsById,
            &$geminiMediaCalls,
        ) {
            $url = (string) $request->url();
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

            if (str_contains($url, 'graph.facebook.com/v19.0/')) {
                $id = basename($path);
                $graphMetaCallsById[$id] = ($graphMetaCallsById[$id] ?? 0) + 1;

                return Http::response([
                    'url' => 'https://graph.facebook.com/media/'.$id,
                    'mime_type' => $mediaIdToMime[$id] ?? 'application/octet-stream',
                ]);
            }

            if (str_contains($url, 'graph.facebook.com/media/')) {
                $id = basename($path);
                $graphDownloadCallsById[$id] = ($graphDownloadCallsById[$id] ?? 0) + 1;

                return Http::response($mediaIdToBytes[$id] ?? '', 200);
            }

            if (str_contains($url, 'generativelanguage.googleapis.com')) {
                $geminiMediaCalls++;

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'gemini transcript'],
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response('unexpected http call to '.$url, 500);
        });

        foreach ($cases as $i => $case) {
            [$mimeType, $expectedAttachmentType, $expectedTranscript, $filename, $shouldCallMediaAi] = $case;

            $currentFilename = $filename;
            $currentExpectedTranscript = $expectedTranscript;
            $currentShouldCallMediaAi = $shouldCallMediaAi;

            $phone = $basePhone.'_'.$i;
            $customer = Customer::factory()->create([
                'name' => 'Known Customer '.$i,
                'phone' => $phone,
                'daily_ai_cost_limit' => 100,
            ]);

            $mediaId = 'media_'.$i;
            $caption = 'Please process';
            $payload = WhatsAppInboundPayload::media(
                fromPhone: $customer->phone,
                profileName: $customer->name,
                mimeType: $mimeType,
                caption: $caption,
                filename: $filename,
                mediaId: $mediaId,
            );

            $metaBefore = $graphMetaCallsById[$mediaId] ?? 0;
            $downloadBefore = $graphDownloadCallsById[$mediaId] ?? 0;
            $geminiBefore = $geminiMediaCalls;

            $job = new ProcessIncomingMessage($payload, 'whatsapp');
            $job->handle(app(CustomerService::class), app(ConversationService::class));

            $attachment = Attachment::query()->where('original_name', $filename)->firstOrFail();
            $this->assertSame($expectedAttachmentType, $attachment->type);
            $this->assertNotEmpty($attachment->stored_path);
            $this->assertTrue(Storage::disk('local')->exists($attachment->stored_path));

            if ($shouldCallMediaAi) {
                $this->assertNotEmpty($attachment->ai_transcript);
                $this->assertSame($expectedTranscript, $attachment->ai_transcript);
            } else {
                $this->assertEmpty($attachment->ai_transcript);
            }

            $this->assertSame(1, ($graphMetaCallsById[$mediaId] ?? 0) - $metaBefore, 'Expected exactly one WhatsApp media metadata call.');
            $this->assertSame(1, ($graphDownloadCallsById[$mediaId] ?? 0) - $downloadBefore, 'Expected exactly one WhatsApp media bytes call.');

            $geminiDelta = $geminiMediaCalls - $geminiBefore;

            if ($shouldCallMediaAi) {
                $this->assertSame(1, $geminiDelta, 'Expected Gemini media transcription for voice notes.');
            } else {
                $this->assertSame(0, $geminiDelta);
            }
        }
    }

    public function test_image_transcript_is_only_sent_to_ai_when_attachment_analysis_is_opted_in(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'name' => 'Known Customer',
            'phone' => '15550000001',
            'daily_ai_cost_limit' => 100,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            if (str_contains($url, 'graph.facebook.com/v19.0/')) {
                return Http::response([
                    'url' => 'https://graph.facebook.com/media/opt_in_media',
                    'mime_type' => 'image/jpeg',
                ]);
            }
            if (str_contains($url, 'graph.facebook.com/media/opt_in_media')) {
                return Http::response('image-bytes', 200);
            }
            if (str_contains($url, 'generativelanguage.googleapis.com')) {
                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'vision transcript'],
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response('unexpected http call', 500);
        });

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturnUsing(function () {
                    $args = func_get_args();
                    $messageBody = $args[0] ?? '';
                    $this->assertStringContainsString('[sample.jpg transcript] vision transcript', $messageBody);

                    return AiRequestLog::create([
                        'uuid' => (string) Str::uuid(),
                        'validation_status' => 'passed',
                        'parsed_json' => [
                            'is_actionable' => false,
                            'intent' => 'media',
                            'confidence' => 0.0,
                            'summary' => 'No action',
                            'project' => '',
                            'tasks' => [],
                        ],
                        'provider' => 'mock',
                        'model_name' => 'test',
                        'cost' => 0,
                        'total_tokens' => 0,
                    ]);
                });
        });

        $payload = WhatsAppInboundPayload::media(
            fromPhone: $customer->phone,
            profileName: $customer->name,
            mimeType: 'image/jpeg',
            caption: 'Please inspect image',
            filename: 'sample.jpg',
            mediaId: 'opt_in_media',
            aiControls: [
                'analyze_attachments' => true,
            ],
        );

        $job = new ProcessIncomingMessage($payload, 'whatsapp');
        $job->handle(app(CustomerService::class), app(ConversationService::class));

        $attachment = Attachment::query()->where('original_name', 'sample.jpg')->firstOrFail();
        $this->assertSame('vision transcript', $attachment->ai_transcript);
    }

    public function test_unknown_whatsapp_number_media_downloads_but_holds_ai_for_verification(): void
    {
        Storage::fake('local');

        $mediaAiCalls = 0;

        Http::fake(function ($request) use (&$mediaAiCalls) {
            $url = (string) $request->url();
            if (str_contains($url, 'api.openai.com') || str_contains($url, 'generativelanguage.googleapis.com')) {
                $mediaAiCalls++;
            }
            if (str_contains($url, 'graph.facebook.com/v19.0/unknown_media_id')) {
                return Http::response([
                    'url' => 'https://graph.facebook.com/media/unknown_media_id',
                    'mime_type' => 'image/jpeg',
                ], 200);
            }
            if (str_contains($url, 'graph.facebook.com/media/unknown_media_id')) {
                return Http::response('fake-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(['messages' => [['id' => 'wamid.out']]], 200);
        });

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->never();
        });

        $payload = WhatsAppInboundPayload::media(
            fromPhone: '15551239999', // unknown
            profileName: 'Unknown Sender',
            mimeType: 'image/jpeg',
            caption: 'Should download and hold',
            filename: 'sample.jpg',
            mediaId: 'unknown_media_id',
        );

        $job = new ProcessIncomingMessage($payload, 'whatsapp');
        $job->handle(app(CustomerService::class), app(ConversationService::class));

        $this->assertSame(0, $mediaAiCalls);
        $this->assertCount(1, Attachment::query()->get());

        $conversation = Conversation::query()->firstOrFail();
        $message = Message::query()->where('direction', 'inbound')->firstOrFail();
        $session = ConversationSession::query()->firstOrFail();

        $this->assertSame('whatsapp', $conversation->channel);
        $this->assertSame('received', $message->status->value);
        $this->assertSame(ConversationSessionStatus::AwaitingVerification, $session->status);
        $this->assertNull(Issue::query()->first(), 'Unknown-number branch should not create a draft issue.');
    }

    public function test_boss_whatsapp_media_skips_download_and_attachments(): void
    {
        Storage::fake('local');

        Employee::factory()->create([
            'name' => 'Boss User',
            'phone' => '+9647700000000',
            'role' => 'Executive Boss',
        ]);

        $graphCalls = 0;
        $mediaAiCalls = 0;

        Http::fake(function ($request) use (&$graphCalls, &$mediaAiCalls) {
            $url = (string) $request->url();
            if (str_contains($url, 'graph.facebook.com')) {
                $graphCalls++;
            }
            if (str_contains($url, 'api.openai.com') || str_contains($url, 'generativelanguage.googleapis.com')) {
                $mediaAiCalls++;
            }

            return Http::response('unexpected http call', 500);
        });

        $this->mock(AIManager::class, function ($mock) {
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () {
                $args = func_get_args();
                $messageBody = $args[0] ?? '';

                $this->assertStringContainsString('Boss pic', $messageBody);

                $context = $args[6] ?? [];

                return AiRequestLog::create([
                    'uuid' => (string) Str::uuid(),
                    'validation_status' => 'passed',
                    'parsed_json' => [
                        'is_actionable' => false,
                        'intent' => 'boss',
                        'confidence' => 0.0,
                        'summary' => 'No action',
                        'project' => '',
                        'tasks' => [],
                    ],
                    'provider' => 'mock',
                    'model_name' => 'test',
                    'cost' => 0,
                    'total_tokens' => 0,
                    'customer_id' => $context['customer_id'] ?? null,
                    'conversation_id' => $context['conversation_id'] ?? null,
                    'conversation_session_id' => $context['conversation_session_id'] ?? null,
                    'source' => $context['source'] ?? 'conversation',
                ]);
            });
        });

        $payload = WhatsAppInboundPayload::media(
            fromPhone: '+9647700000000', // seeded boss phone
            profileName: 'Boss User',
            mimeType: 'image/jpeg',
            caption: 'Boss pic',
            filename: 'boss.jpg',
            mediaId: 'boss_media_id',
        );

        $job = new ProcessIncomingMessage($payload, 'whatsapp');
        $job->handle(app(CustomerService::class), app(ConversationService::class));

        $this->assertSame(0, $graphCalls, 'Boss branch should not enqueue DownloadAttachment.');
        $this->assertSame(0, $mediaAiCalls);
        $this->assertCount(0, Attachment::query()->get());
    }
}
