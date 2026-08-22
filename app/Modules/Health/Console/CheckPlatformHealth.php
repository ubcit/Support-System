<?php

namespace Modules\Health\Console;

use Illuminate\Console\Command;
use Modules\Health\Services\HealthEngine;

class CheckPlatformHealth extends Command
{
    protected $signature = 'health:check';
    protected $description = 'Run a complete platform health check';

    public function handle(HealthEngine $engine): int
    {
        $this->info('Evaluating Platform Health...');
        
        $report = $engine->evaluateHealth();
        
        foreach ($report['checks'] as $name => $result) {
            $status = $result['status'];
            $metadata = json_encode($result['metadata']);
            
            $message = str_pad("[$name]", 15) . " => $status \t $metadata";
            
            if ($status === 'Healthy') {
                $this->info($message);
            } elseif ($status === 'Critical') {
                $this->error($message);
            } else {
                $this->warn($message);
            }
        }
        
        $this->newLine();
        
        $overall = $report['overall_status'];
        if ($overall === 'Healthy') {
            $this->info("OVERALL PLATFORM STATUS: {$overall}");
            return 0;
        } else {
            $this->error("OVERALL PLATFORM STATUS: {$overall}");
            return 1;
        }
    }
}
