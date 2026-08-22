<?php

namespace Modules\Tasks\Services;

use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskTimeLog;

class TimeTrackingService
{
    public function startTimer(Task $task, Employee $employee, ?string $description = null): TaskTimeLog
    {
        // Stop any running timer for this employee first
        TaskTimeLog::where('employee_id', $employee->id)
            ->where('is_running', true)
            ->get()
            ->each(fn ($log) => $this->stopTimer($log));

        return TaskTimeLog::create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'start_time' => now(),
            'is_running' => true,
            'description' => $description,
        ]);
    }

    public function stopTimer(TaskTimeLog $log): TaskTimeLog
    {
        if (!$log->is_running) {
            return $log;
        }

        $endTime = now();
        $durationMinutes = (int) ceil($log->start_time->diffInMinutes($endTime));

        $log->update([
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'is_running' => false,
        ]);

        $this->recalculateTaskActualHours($log->task);

        return $log;
    }

    public function logTimeManual(Task $task, Employee $employee, int $durationMinutes, ?string $description = null): TaskTimeLog
    {
        $startTime = now()->subMinutes($durationMinutes);
        $endTime = now();

        $log = TaskTimeLog::create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'is_running' => false,
            'description' => $description,
        ]);

        $this->recalculateTaskActualHours($task);

        return $log;
    }

    public function recalculateTaskActualHours(Task $task): void
    {
        $totalMinutes = TaskTimeLog::where('task_id', $task->id)->sum('duration_minutes');
        $actualHours = round($totalMinutes / 60, 2);

        $task->update(['actual_hours' => $actualHours]);
    }
}
