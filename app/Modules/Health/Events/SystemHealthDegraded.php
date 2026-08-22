<?php

namespace Modules\Health\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemHealthDegraded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $previousStatus,
        public string $newStatus,
        public array $report
    ) {}
}
