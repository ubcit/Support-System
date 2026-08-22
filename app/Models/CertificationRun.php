<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CertificationRun extends Model {
    protected $fillable = ['certification_test_id', 'pipeline_log_id', 'benchmark_session_id', 'ai_model_id', 'duration_ms', 'ai_provider', 'sync_provider', 'success', 'score', 'started_at', 'finished_at'];
    protected $casts = ['success' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'score' => 'float'];
    public function test() { return $this->belongsTo(CertificationTest::class, 'certification_test_id'); }
    public function assertions() { return $this->hasMany(CertificationAssertion::class); }
    public function pipelineLog() { return $this->belongsTo(PipelineLog::class); }
    public function benchmarkSession() { return $this->belongsTo(BenchmarkSession::class); }
    public function aiModel() { return $this->belongsTo(AiModel::class); }
}
