<?php

namespace App\Livewire\AiCenter;

use Livewire\Component;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiSchema;
use App\Models\AiRequestLog;

class Index extends Component
{
    public string $activeTab = 'models';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $models = AiModel::all();
        $prompts = AiPrompt::all();
        $schemas = AiSchema::all();
        $logs = AiRequestLog::orderBy('created_at', 'desc')->limit(15)->get();

        $totalTokens = (int) AiRequestLog::sum('total_tokens');
        $avgLatencyMs = (float) (AiRequestLog::avg('latency_ms') ?? 120);
        $totalRequests = AiRequestLog::count();
        $dbCost = (float) AiRequestLog::sum('cost');
        $estimatedCostUsd = number_format($dbCost > 0 ? $dbCost : ($totalTokens / 1000) * 0.002, 4);

        return view('livewire.ai-center.index', [
            'models' => $models,
            'prompts' => $prompts,
            'schemas' => $schemas,
            'logs' => $logs,
            'totalTokens' => $totalTokens,
            'avgLatencyMs' => round($avgLatencyMs, 2),
            'totalRequests' => $totalRequests,
            'estimatedCostUsd' => $estimatedCostUsd,
        ]);
    }
}
