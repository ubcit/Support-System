<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_sessions')) {
            Schema::create('conversation_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('collecting');
                $table->string('title')->nullable();
                $table->text('summary')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->json('analysis')->nullable();
                $table->unsignedBigInteger('request_log_id')->nullable();
                $table->json('task_ids')->nullable();
                $table->boolean('auto_created')->default(false);
                $table->boolean('needs_review')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'status']);
                $table->index('status');
                $table->index('last_message_at');
            });
        }

        if (! Schema::hasColumn('messages', 'conversation_session_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('conversation_session_id')
                    ->nullable()
                    ->after('conversation_id')
                    ->constrained('conversation_sessions')
                    ->nullOnDelete();
            });
        }

        $this->backfillFromMetadata();
        $this->backfillOrphanMessages();
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_session_id');
        });
        Schema::dropIfExists('conversation_sessions');
    }

    protected function backfillFromMetadata(): void
    {
        $conversations = DB::table('conversations')->whereNotNull('metadata')->get();

        foreach ($conversations as $conversation) {
            $metadata = json_decode((string) $conversation->metadata, true);
            if (! is_array($metadata) || empty($metadata['sessions']) || ! is_array($metadata['sessions'])) {
                continue;
            }

            foreach ($metadata['sessions'] as $session) {
                if (! is_array($session)) {
                    continue;
                }

                $analysis = is_array($session['analysis'] ?? null) ? $session['analysis'] : null;
                $taskIds = array_values(array_filter($session['task_ids'] ?? []));
                $title = is_string($analysis['title'] ?? null)
                    ? $analysis['title']
                    : (is_string($analysis['tasks'][0]['title'] ?? null)
                        ? $analysis['tasks'][0]['title']
                        : (is_string($analysis['summary'] ?? null) ? $analysis['summary'] : null));
                $status = ! empty($session['needs_review'])
                    ? 'needs_review'
                    : (! empty($taskIds) || ! empty($session['auto_created']) ? 'open' : 'done');

                $sessionId = DB::table('conversation_sessions')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'conversation_id' => $conversation->id,
                    'status' => $status,
                    'title' => $title,
                    'summary' => is_string($analysis['summary'] ?? null) ? $analysis['summary'] : null,
                    'started_at' => $this->timestamp($session['started_at'] ?? $conversation->created_at),
                    'ended_at' => $this->timestamp($session['ended_at'] ?? $conversation->updated_at),
                    'last_message_at' => $this->timestamp($session['ended_at'] ?? $conversation->last_message_at),
                    'last_read_at' => $this->timestamp($conversation->last_read_at ?? null),
                    'analysis' => $analysis ? json_encode($analysis) : null,
                    'request_log_id' => $session['request_log_id'] ?? null,
                    'task_ids' => json_encode($taskIds),
                    'auto_created' => (bool) ($session['auto_created'] ?? false),
                    'needs_review' => (bool) ($session['needs_review'] ?? false),
                    'metadata' => json_encode([
                        'migrated_from_json' => true,
                        'error' => $session['error'] ?? null,
                        'confidence' => $session['confidence'] ?? null,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $messageIds = array_filter($session['message_ids'] ?? []);
                if ($messageIds !== []) {
                    DB::table('messages')
                        ->where('conversation_id', $conversation->id)
                        ->whereIn('id', $messageIds)
                        ->update(['conversation_session_id' => $sessionId]);
                }
            }
        }
    }

    protected function backfillOrphanMessages(): void
    {
        $conversationIds = DB::table('messages')
            ->whereNull('conversation_session_id')
            ->distinct()
            ->pluck('conversation_id');

        foreach ($conversationIds as $conversationId) {
            $conversation = DB::table('conversations')->where('id', $conversationId)->first();
            if (! $conversation) {
                continue;
            }

            $first = DB::table('messages')
                ->where('conversation_id', $conversationId)
                ->whereNull('conversation_session_id')
                ->orderBy('created_at')
                ->first();
            $last = DB::table('messages')
                ->where('conversation_id', $conversationId)
                ->whereNull('conversation_session_id')
                ->orderByDesc('created_at')
                ->first();

            $sessionId = DB::table('conversation_sessions')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'status' => 'open',
                'title' => null,
                'summary' => null,
                'started_at' => $this->timestamp($first?->created_at ?? $conversation->created_at),
                'ended_at' => null,
                'last_message_at' => $this->timestamp($last?->created_at ?? $conversation->last_message_at),
                'last_read_at' => $this->timestamp($conversation->last_read_at),
                'analysis' => null,
                'request_log_id' => null,
                'task_ids' => json_encode([]),
                'auto_created' => false,
                'needs_review' => false,
                'metadata' => json_encode(['migrated_orphan_messages' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('messages')
                ->where('conversation_id', $conversationId)
                ->whereNull('conversation_session_id')
                ->update(['conversation_session_id' => $sessionId]);
        }
    }

    protected function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
};
