<?php

namespace Modules\Tasks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AI\DTOs\AIResult;
use Modules\Issues\Models\Issue;
use Modules\Tasks\Services\WorkManagementEngine;

class RunWorkManagementEngine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public Issue $issue,
        public AIResult $aiResult
    ) {}

    public function handle(WorkManagementEngine $engine): void
    {
        Log::info("RunWorkManagementEngine Job started for Issue {$this->issue->uuid}");
        
        $engine->generateWorkItems($this->issue, $this->aiResult);
    }
}
