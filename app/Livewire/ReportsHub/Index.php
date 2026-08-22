<?php

namespace App\Livewire\ReportsHub;

use Livewire\Component;
use Modules\Statistics\Services\ReportsService;

class Index extends Component
{
    public string $tab = 'overview';

    public string $period = '30d';

    protected function queryString(): array
    {
        return [
            'tab' => ['except' => 'overview'],
            'period' => ['except' => '30d'],
        ];
    }

    public function setTab(string $tab): void
    {
        $allowed = ['overview', 'tasks', 'employees', 'customers', 'ai-cost'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'overview';
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, ReportsService::periodOptions()) ? $period : '30d';
    }

    public function render()
    {
        $reports = new ReportsService($this->period);
        $payload = match ($this->tab) {
            'tasks' => ['tasks' => $reports->tasks()],
            'employees' => ['employees' => $reports->employees()],
            'customers' => ['customers' => $reports->customers()],
            'ai-cost' => ['ai_cost' => $reports->aiCost()],
            default => ['overview' => $reports->overview()],
        };

        $chartData = $payload['overview']['chart_data'] ?? $payload['tasks']['chart_data'] ?? collect();

        return view('livewire.reports-hub.index', array_merge($payload, [
            'period_options' => ReportsService::periodOptions(),
            'chart_data' => $chartData,
            'max_chart_value' => $reports->maxChartValue($chartData),
            'reports' => $reports,
        ]));
    }
}
