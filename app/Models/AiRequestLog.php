<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Customers\Models\Customer;
use Modules\Projects\Models\Project;

/**
 * Business-level log of an AI Center request: links a pipeline run to the
 * `AiModel`/`AiPrompt` used, token/cost accounting, and validation status.
 * Written by the mock/scoring pipeline (`AIManager`, `AiScoringEngine`) and
 * read by the Ai Center / Request Logs admin UI.
 *
 * This is intentionally a *different* concern from `Modules\AI\Models\AILog`,
 * which is a low-level log of raw OpenAI API calls written directly by
 * `OpenAIProvider`. Do not assume these two tables are redundant duplicates —
 * see project-roadmap.md Phase 5 for the investigation that reached this
 * conclusion before deciding whether to consolidate further.
 */
class AiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'pipeline_log_id', 'ai_model_id', 'ai_prompt_id',
        'customer_id', 'conversation_id', 'conversation_session_id', 'project_id', 'source',
        'provider', 'model_name', 'prompt_tokens', 'completion_tokens',
        'total_tokens', 'cost', 'latency_ms', 'raw_request',
        'raw_response', 'parsed_json', 'validation_status', 'error_message',
    ];

    protected $casts = [
        'raw_request' => 'array',
        'raw_response' => 'array',
        'parsed_json' => 'array',
        'cost' => 'float',
    ];

    public function pipelineLog(): BelongsTo
    {
        return $this->belongsTo(PipelineLog::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    public function aiPrompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function conversationSession(): BelongsTo
    {
        return $this->belongsTo(ConversationSession::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getTokensAttribute(): int
    {
        return $this->total_tokens ?? 0;
    }
}
