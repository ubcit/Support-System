<?php

namespace Modules\Rules\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Rules\Services\RulesEngine;
use Illuminate\Support\Str;

class EvaluateRules implements ShouldQueue
{
    public function __construct(
        protected RulesEngine $rulesEngine
    ) {}

    public function handle($event): void
    {
        // Extract the short class name of the event (e.g. TaskStateChanged)
        $eventName = class_basename($event);

        // Convert the event object to an array context via json serialize
        $context = json_decode(json_encode($event), true);

        $this->rulesEngine->evaluateEvent($eventName, $context, false);
    }
}
