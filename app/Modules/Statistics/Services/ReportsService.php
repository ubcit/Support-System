<?php

namespace Modules\Statistics\Services;

use App\Models\AiRequestLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\ConversationSession;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerAiBudgetService;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskTimeLog;

class ReportsService
{
    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            'month' => 'This month',
        ];
    }

    public function __construct(
        public string $period = '30d'
    ) {
        if (! array_key_exists($this->period, self::periodOptions())) {
            $this->period = '30d';
        }
    }

    public function from(): Carbon
    {
        $now = Carbon::now();

        return match ($this->period) {
            '7d' => $now->copy()->subDays(7)->startOfDay(),
            'month' => $now->copy()->startOfMonth(),
            default => $now->copy()->subDays(30)->startOfDay(),
        };
    }

    public function overview(): array
    {
        $from = $this->from();
        $budget = app(CustomerAiBudgetService::class);

        $createdInPeriod = Task::query()->where('created_at', '>=', $from)->count();
        $completedInPeriod = Task::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->count();
        $totalTasks = Task::query()->count();
        $completedTotal = Task::query()->whereNotNull('completed_at')->count();

        $todaySpend = (float) AiRequestLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('cost');

        $overBudget = 0;
        $spentToday = AiRequestLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(cost) as spend')
            ->groupBy('customer_id')
            ->pluck('spend', 'customer_id');

        $customers = Customer::query()->select('id', 'daily_ai_cost_limit')->get();
        $workspaceDefault = $budget->workspaceDefaultLimit();
        foreach ($customers as $customer) {
            $limit = $customer->daily_ai_cost_limit ?? $workspaceDefault;
            if ($limit === null) {
                continue;
            }
            $spend = (float) ($spentToday[$customer->id] ?? 0);
            if ($spend >= (float) $limit) {
                $overBudget++;
            }
        }

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTotal,
            'period_created' => $createdInPeriod,
            'period_completed' => $completedInPeriod,
            'completion_rate' => $createdInPeriod > 0
                ? round(($completedInPeriod / $createdInPeriod) * 100, 1)
                : ($totalTasks > 0 ? round(($completedTotal / $totalTasks) * 100, 1) : 100),
            'avg_response_time' => $this->formatMinutes($this->averageResolutionMinutes($from)),
            'total_employees' => Employee::query()->count(),
            'total_projects' => Project::query()->where('status', 'active')->count(),
            'chart_data' => $this->velocityChart(),
            'today_ai_spend' => $todaySpend,
            'customers_over_budget' => $overBudget,
            'needs_review_sessions' => ConversationSession::query()
                ->where('status', ConversationSessionStatus::NeedsReview->value)
                ->count(),
        ];
    }

    public function tasks(): array
    {
        $from = $this->from();
        $createdInPeriod = Task::query()->where('created_at', '>=', $from)->count();
        $completedInPeriod = Task::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->count();

        $open = Task::query()->whereNull('completed_at');

        $byPriority = Task::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $byProject = Task::query()
            ->where('tasks.created_at', '>=', $from)
            ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->selectRaw("COALESCE(projects.name, 'Unassigned') as project_name, COUNT(tasks.id) as total")
            ->groupBy('project_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'period_created' => $createdInPeriod,
            'period_completed' => $completedInPeriod,
            'completion_rate' => $createdInPeriod > 0
                ? round(($completedInPeriod / $createdInPeriod) * 100, 1)
                : 100,
            'overdue' => (clone $open)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
            'unassigned' => (clone $open)->whereDoesntHave('assignees')->count(),
            'avg_response_time' => $this->formatMinutes($this->averageResolutionMinutes($from)),
            'chart_data' => $this->velocityChart(),
            'by_priority' => $byPriority,
            'by_project' => $byProject,
        ];
    }

    public function employees(): Collection
    {
        $from = $this->from();
        $employees = Employee::query()->orderBy('name')->get();
        $activeCounts = DB::table('task_assignments')
            ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('task_assignments.unassigned_at')
            ->whereNull('tasks.completed_at')
            ->selectRaw('task_assignments.employee_id, COUNT(DISTINCT tasks.id) as total')
            ->groupBy('task_assignments.employee_id')
            ->pluck('total', 'employee_id');

        $completedCounts = DB::table('task_assignments')
            ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('task_assignments.unassigned_at')
            ->whereNotNull('tasks.completed_at')
            ->where('tasks.completed_at', '>=', $from)
            ->selectRaw('task_assignments.employee_id, COUNT(DISTINCT tasks.id) as total')
            ->groupBy('task_assignments.employee_id')
            ->pluck('total', 'employee_id');

        $overdueCounts = DB::table('task_assignments')
            ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('task_assignments.unassigned_at')
            ->whereNull('tasks.completed_at')
            ->whereNotNull('tasks.due_date')
            ->whereDate('tasks.due_date', '<', now()->toDateString())
            ->selectRaw('task_assignments.employee_id, COUNT(DISTINCT tasks.id) as total')
            ->groupBy('task_assignments.employee_id')
            ->pluck('total', 'employee_id');

        $loggedMinutes = TaskTimeLog::query()
            ->where('task_time_logs.created_at', '>=', $from)
            ->selectRaw('employee_id, SUM(duration_minutes) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        return $employees->map(function (Employee $employee) use ($activeCounts, $completedCounts, $overdueCounts, $loggedMinutes) {
            $active = (int) ($activeCounts[$employee->id] ?? 0);
            $max = (int) ($employee->max_workload ?: 10);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role,
                'active' => $active,
                'completed' => (int) ($completedCounts[$employee->id] ?? 0),
                'overdue' => (int) ($overdueCounts[$employee->id] ?? 0),
                'max_workload' => $max,
                'load_label' => $active > $max ? 'Heavy' : 'OK',
                'logged_hours' => round(((int) ($loggedMinutes[$employee->id] ?? 0)) / 60, 1),
            ];
        });
    }

    public function customers(): Collection
    {
        $from = $this->from();
        $budget = app(CustomerAiBudgetService::class);
        $workspaceDefault = $budget->workspaceDefaultLimit();

        $conversationCounts = Conversation::query()
            ->where('updated_at', '>=', $from)
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $sessionStats = ConversationSession::query()
            ->join('conversations', 'conversations.id', '=', 'conversation_sessions.conversation_id')
            ->where('conversation_sessions.created_at', '>=', $from)
            ->selectRaw('conversations.customer_id, COUNT(*) as sessions_count, SUM(CASE WHEN conversation_sessions.needs_review = 1 THEN 1 ELSE 0 END) as review_count')
            ->groupBy('conversations.customer_id')
            ->get()
            ->keyBy('customer_id');

        $spend = AiRequestLog::query()
            ->where('created_at', '>=', $from)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(cost) as spend')
            ->groupBy('customer_id')
            ->pluck('spend', 'customer_id');

        $todaySpend = AiRequestLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(cost) as spend')
            ->groupBy('customer_id')
            ->pluck('spend', 'customer_id');

        $taskCounts = Task::query()
            ->where('tasks.created_at', '>=', $from)
            ->whereNotNull('tasks.project_id')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->selectRaw('projects.customer_id, COUNT(tasks.id) as total')
            ->groupBy('projects.customer_id')
            ->pluck('total', 'customer_id');

        return Customer::query()
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) use ($conversationCounts, $sessionStats, $spend, $todaySpend, $taskCounts, $workspaceDefault) {
                $limit = $customer->daily_ai_cost_limit ?? $workspaceDefault;
                $spentToday = (float) ($todaySpend[$customer->id] ?? 0);
                $overBudget = $limit !== null && $spentToday >= (float) $limit;
                $stats = $sessionStats[$customer->id] ?? null;

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'conversations' => (int) ($conversationCounts[$customer->id] ?? 0),
                    'sessions' => (int) ($stats->sessions_count ?? 0),
                    'needs_review' => (int) ($stats->review_count ?? 0),
                    'tasks' => (int) ($taskCounts[$customer->id] ?? 0),
                    'period_spend' => (float) ($spend[$customer->id] ?? 0),
                    'today_spend' => $spentToday,
                    'daily_limit' => $limit,
                    'over_budget' => $overBudget,
                ];
            });
    }

    public function aiCost(): array
    {
        $from = $this->from();
        $logs = AiRequestLog::query()->where('created_at', '>=', $from);

        $customerRows = (clone $logs)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(cost) as spend, SUM(total_tokens) as tokens, COUNT(*) as requests')
            ->groupBy('customer_id')
            ->orderByDesc('spend')
            ->limit(10)
            ->get();

        $customers = Customer::query()
            ->whereIn('id', $customerRows->pluck('customer_id'))
            ->get()
            ->keyBy('id');

        $projectRows = (clone $logs)
            ->whereNotNull('project_id')
            ->selectRaw('project_id, SUM(cost) as spend, SUM(total_tokens) as tokens, COUNT(*) as requests')
            ->groupBy('project_id')
            ->orderByDesc('spend')
            ->limit(10)
            ->get();

        $projects = Project::query()
            ->whereIn('id', $projectRows->pluck('project_id'))
            ->get()
            ->keyBy('id');

        $sessions = ConversationSession::query()
            ->with(['conversation.customer', 'requestLog'])
            ->join('ai_request_logs', 'ai_request_logs.id', '=', 'conversation_sessions.request_log_id')
            ->where('ai_request_logs.created_at', '>=', $from)
            ->orderByDesc('ai_request_logs.cost')
            ->select('conversation_sessions.*')
            ->limit(20)
            ->get();

        $sessionProjectIds = $sessions
            ->flatMap(fn (ConversationSession $session) => $session->taskIdList())
            ->unique()
            ->values();
        $taskProjects = $sessionProjectIds->isEmpty()
            ? collect()
            : Task::query()->whereIn('id', $sessionProjectIds)->pluck('project_id', 'id');
        $projectNames = Project::query()
            ->whereIn('id', $taskProjects->filter()->unique()->values()->merge($projectRows->pluck('project_id')))
            ->pluck('name', 'id');

        return [
            'spend' => (float) (clone $logs)->sum('cost'),
            'tokens' => (int) (clone $logs)->sum('total_tokens'),
            'requests' => (clone $logs)->count(),
            'customers' => $customerRows->map(fn ($row) => [
                'id' => $row->customer_id,
                'name' => $customers[$row->customer_id]->name ?? 'Unknown',
                'spend' => (float) $row->spend,
                'tokens' => (int) $row->tokens,
                'requests' => (int) $row->requests,
            ]),
            'projects' => $projectRows->map(fn ($row) => [
                'id' => $row->project_id,
                'name' => $projects[$row->project_id]->name ?? 'Unknown',
                'spend' => (float) $row->spend,
                'tokens' => (int) $row->tokens,
                'requests' => (int) $row->requests,
            ]),
            'sessions' => $sessions->map(function (ConversationSession $session) use ($taskProjects, $projectNames) {
                $taskId = $session->taskIdList()[0] ?? null;
                $projectId = $session->requestLog?->project_id ?: ($taskId ? ($taskProjects[$taskId] ?? null) : null);

                return [
                    'id' => $session->id,
                    'title' => $session->displayTitle(),
                    'customer' => $session->conversation?->customer?->name ?? 'Unknown',
                    'project' => $projectId ? ($projectNames[$projectId] ?? '—') : '—',
                    'tokens' => (int) ($session->requestLog?->total_tokens ?? 0),
                    'cost' => (float) ($session->requestLog?->cost ?? 0),
                    'status' => $session->status instanceof ConversationSessionStatus
                        ? $session->status->value
                        : (string) $session->status,
                    'url' => route('conversation-center', ['session' => $session->id]),
                ];
            }),
        ];
    }

    /**
     * @return Collection<int, array{label: string, value: int}>
     */
    public function velocityChart(): Collection
    {
        $chart = collect();
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $chart->push([
                'label' => $day->format('M d'),
                'value' => Task::query()
                    ->whereNotNull('completed_at')
                    ->whereDate('completed_at', $day->toDateString())
                    ->count(),
            ]);
        }

        return $chart;
    }

    public function maxChartValue(Collection $chartData): int
    {
        return max(1, (int) $chartData->max('value'));
    }

    public function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 4);
    }

    public function formatMinutes(?float $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '—';
        }

        $rounded = (int) round($minutes);

        return $rounded > 60 ? round($rounded / 60, 1).' hrs' : $rounded.' mins';
    }

    protected function averageResolutionMinutes(Carbon $from): ?float
    {
        $driver = DB::connection()->getDriverName();
        $expr = $driver === 'sqlite'
            ? '(julianday(completed_at) - julianday(created_at)) * 1440'
            : 'TIMESTAMPDIFF(MINUTE, created_at, completed_at)';

        $value = Task::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->selectRaw("AVG({$expr}) as avg_minutes")
            ->value('avg_minutes');

        return $value !== null ? (float) $value : null;
    }
}
