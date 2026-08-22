<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Low-level, provider-side log of a raw OpenAI API call (written directly by
 * `Modules\AI\Providers\OpenAIProvider`). Polymorphic `loggable` links it back
 * to whatever domain object triggered the call.
 *
 * This is intentionally a *different* concern from `App\Models\AiRequestLog`,
 * which records business-level AI Center requests (pipeline/model/prompt
 * bookkeeping, validation status) as seen by the mock/scoring pipeline
 * (`AIManager`, `AiScoringEngine`) and the Ai Center / Request Logs admin UI.
 * Do not assume these two tables are redundant duplicates — see
 * project-roadmap.md Phase 5 for the investigation that reached this
 * conclusion before deciding whether to consolidate further.
 */
class AILog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_logs';

    protected $fillable = [
        'provider',
        'model',
        'action',
        'input_text',
        'output_text',
        'input_tokens',
        'output_tokens',
        'cost_cents',
        'duration_ms',
        'loggable_type',
        'loggable_id',
        'metadata',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_cents' => 'decimal:4',
        'duration_ms' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
