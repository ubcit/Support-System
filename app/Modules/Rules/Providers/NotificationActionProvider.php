<?php

namespace Modules\Rules\Providers;

use Illuminate\Support\Facades\Log;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Services\NotificationService;
use Modules\Rules\Contracts\ActionInterface;

class NotificationActionProvider implements ActionInterface
{
    public function getName(): string
    {
        return 'notify';
    }

    public function execute(array $actionConfig, array $eventContext, bool $isSimulation = false): void
    {
        $target = $actionConfig['target'] ?? 'unknown';
        $channel = $actionConfig['channel'] ?? 'in_app';
        $title = $actionConfig['title'] ?? ($eventContext['title'] ?? 'Notification');
        $body = $actionConfig['body'] ?? ($eventContext['summary'] ?? $eventContext['body'] ?? '');

        if ($isSimulation) {
            Log::info("[SIMULATION] Would have dispatched notification to {$target} via {$channel}");

            return;
        }

        $employee = $this->resolveEmployee($actionConfig, $eventContext, $target);
        if (! $employee) {
            Log::info("NotificationActionProvider: no employee for target {$target}");

            return;
        }

        app(NotificationService::class)->send(
            title: (string) $title,
            body: is_string($body) ? $body : '',
            type: $actionConfig['notification_type'] ?? 'general',
            employee: $employee,
            userId: $employee?->user_id,
            channel: is_string($channel) ? $channel : 'in_app',
            actionUrl: $actionConfig['action_url'] ?? ($eventContext['action_url'] ?? null),
            metadata: [
                'target' => $target,
                'event' => $eventContext,
            ],
        );
    }

    protected function resolveEmployee(array $actionConfig, array $eventContext, mixed $target): ?Employee
    {
        $employeeId = $actionConfig['employee_id'] ?? $eventContext['employee_id'] ?? null;
        if (is_numeric($employeeId)) {
            return Employee::find($employeeId);
        }

        if (is_numeric($target)) {
            return Employee::find($target);
        }

        if (is_string($target) && str_contains($target, '@')) {
            return Employee::where('email', $target)->first();
        }

        return null;
    }
}
