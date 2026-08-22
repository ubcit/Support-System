<?php

namespace App\Modules\Core\Services;

class WizardRegistry
{
    protected array $steps = [];

    /**
     * Register a setup step into the wizard.
     * 
     * @param string $id The unique step ID (e.g., 'ai_config')
     * @param array $formSchema The form field schema for this step
     * @param callable $validationLogic Validates the step data
     * @param callable $completionCallback Executes when the wizard completes successfully
     * @param int $priority Order priority (lower is earlier)
     */
    public function registerStep(
        string $id, 
        string $title, 
        string $description, 
        string $icon,
        array $formSchema, 
        callable $validationLogic, 
        callable $completionCallback,
        int $priority = 100
    ) {
        $this->steps[$id] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'schema' => $formSchema,
            'validate' => $validationLogic,
            'complete' => $completionCallback,
            'priority' => $priority
        ];
    }

    /**
     * Get all registered steps sorted by priority
     */
    public function getSteps(): array
    {
        return collect($this->steps)->sortBy('priority')->values()->toArray();
    }
}
