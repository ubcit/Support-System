<?php

namespace Modules\Rules\Contracts;

interface ActionInterface
{
    /**
     * Get the unique name for this action provider (e.g. 'notify', 'assign_employee')
     */
    public function getName(): string;

    /**
     * Execute the action based on the parsed action config and event context.
     */
    public function execute(array $actionConfig, array $eventContext, bool $isSimulation = false): void;
}
