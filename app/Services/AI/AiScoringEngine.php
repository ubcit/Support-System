<?php

namespace App\Services\AI;

use App\Models\CertificationRun;
use App\Models\AiRequestLog;

class AiScoringEngine
{
    public function calculateScore(CertificationRun $run): float
    {
        $score = 0.0;
        
        $aiLog = $run->pipeline_log_id ? AiRequestLog::where('pipeline_log_id', $run->pipeline_log_id)->first() : null;
        
        // 1. Schema Validation (30%)
        if ($aiLog && $aiLog->validation_status === 'passed') {
            $score += 30;
        }

        // 2. Business Assertions (50%)
        $assertions = $run->assertions;
        $totalAssertions = $assertions->count();
        if ($totalAssertions > 0) {
            $passedAssertions = $assertions->where('passed', true)->count();
            $assertionPercentage = $passedAssertions / $totalAssertions;
            $score += ($assertionPercentage * 50);
        } else {
            if ($run->success) {
                $score += 50;
            }
        }

        // 3. Latency (10%)
        $latency = $aiLog ? $aiLog->latency_ms : $run->duration_ms;
        if ($latency <= 1000) {
            $score += 10;
        } elseif ($latency <= 2000) {
            $score += 8;
        } elseif ($latency <= 4000) {
            $score += 5;
        }

        // 4. Cost (10%)
        $cost = $aiLog ? $aiLog->cost : 0.0;
        if ($cost == 0.0) {
            $score += 10;
        } elseif ($cost <= 0.005) {
            $score += 8;
        } elseif ($cost <= 0.01) {
            $score += 5;
        }

        return round($score, 2);
    }
}
