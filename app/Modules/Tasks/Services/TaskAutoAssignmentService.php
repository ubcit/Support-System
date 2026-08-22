<?php

namespace Modules\Tasks\Services;

use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class TaskAutoAssignmentService
{
    /**
     * Auto-assign a task based on explicit request, Project Members, or available staff.
     */
    public static function assignTask(Task $task, ?string $preferredName = null, ?int $assignerEmployeeId = null): ?Employee
    {
        // 1. Try explicit preferred name (e.g. from Boss Notes or AI output)
        if (!empty($preferredName)) {
            $employee = Employee::where('name', 'LIKE', "%{$preferredName}%")->first();
            if ($employee) {
                self::createAssignment($task, $employee->id, $assignerEmployeeId);
                return $employee;
            }
        }

        // 2. Try Project Members (if task is attached to a project)
        if ($task->project_id) {
            $project = Project::with('employees')->find($task->project_id);
            if ($project && $project->employees()->exists()) {
                $employee = $project->employees()->where('is_available', true)->first()
                    ?? $project->employees()->first();

                if ($employee) {
                    self::createAssignment($task, $employee->id, $assignerEmployeeId);
                    return $employee;
                }
            }
        }

        // 3. Prefer an available employee on the same workspace, otherwise
        // leave the task unassigned for a manager to pick up. Never fall back
        // to Employee::first() — that silently dumped WhatsApp work onto
        // whoever happened to have the lowest id.
        $employee = Employee::query()->where('is_available', true)->first();

        if ($employee) {
            self::createAssignment($task, $employee->id, $assignerEmployeeId);

            return $employee;
        }

        return null;
    }

    private static function createAssignment(Task $task, int $employeeId, ?int $assignerEmployeeId = null): void
    {
        $task->assignments()->create([
            'employee_id' => $employeeId,
            'status' => 'accepted',
            'role' => 'assignee',
            'assigned_by' => $assignerEmployeeId,
            'assigned_at' => now(),
        ]);
    }
}
