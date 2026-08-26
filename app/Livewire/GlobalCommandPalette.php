<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class GlobalCommandPalette extends Component
{
    public bool $isOpen = false;

    public string $query = '';

    public array $results = [];

    public int $selectedIndex = 0;

    #[On('open-command-palette')]
    public function open(): void
    {
        // Visibility is Alpine-owned; this only resets search state if something still dispatches the LW event.
        $this->query = '';
        $this->selectedIndex = 0;
        $this->results = [];
        $this->isOpen = true;
    }

    #[On('close-command-palette')]
    public function close(): void
    {
        $this->isOpen = false;
    }

    public function updatedQuery(): void
    {
        $this->selectedIndex = 0;
        $this->search();
    }

    public function executeSelected()
    {
        if (! isset($this->results[$this->selectedIndex])) {
            return;
        }

        $result = $this->results[$this->selectedIndex];

        if (isset($result['url'])) {
            return $this->redirect($result['url'], navigate: true);
        }

        if (isset($result['action'])) {
            $this->close();
        }
    }

    public function search(): void
    {
        $this->results = [];

        if (mb_strlen($this->query) < 2) {
            return;
        }

        $searchQuery = "%{$this->query}%";

        $customers = \Modules\Customers\Models\Customer::query()
            ->where(function ($query) use ($searchQuery) {
                $query->where('name', 'like', $searchQuery)
                    ->orWhere('phone', 'like', $searchQuery);
            })
            ->take(3)->get();
        foreach ($customers as $customer) {
            $this->results[] = [
                'type' => 'Customer',
                'title' => $customer->name,
                'subtitle' => $customer->phone,
                'icon' => 'heroicon-o-building-office',
                'url' => url('/admin/customer-crm'),
            ];
        }

        $projects = \Modules\Projects\Models\Project::where('name', 'like', $searchQuery)->take(3)->get();
        foreach ($projects as $project) {
            $this->results[] = [
                'type' => 'Project',
                'title' => $project->name,
                'subtitle' => 'Status: '.($project->status?->value ?? 'Active'),
                'icon' => 'heroicon-o-briefcase',
                'url' => url('/admin/project-hub?project='.$project->id),
            ];
        }

        $tasks = \Modules\Tasks\Models\Task::where('title', 'like', $searchQuery)->take(5)->get();
        foreach ($tasks as $task) {
            $this->results[] = [
                'type' => 'Task',
                'title' => $task->title,
                'subtitle' => 'Priority: '.($task->priority?->value ?? 'Normal'),
                'icon' => 'heroicon-o-check-circle',
                'url' => url('/admin/task-detail/'.$task->id),
            ];
        }

        $employees = \Modules\Employees\Models\Employee::where(function ($q) use ($searchQuery) {
            $q->where('name', 'like', $searchQuery)
                ->orWhere('email', 'like', $searchQuery);
        })->take(3)->get();
        foreach ($employees as $employee) {
            $this->results[] = [
                'type' => 'Employee',
                'title' => $employee->name ?? 'Unknown',
                'subtitle' => $employee->role ?? 'Employee',
                'icon' => 'heroicon-o-user-group',
                'url' => url('/admin/employee-management'),
            ];
        }

        $issues = \Modules\Issues\Models\Issue::where('title', 'like', $searchQuery)->take(3)->get();
        foreach ($issues as $issue) {
            $this->results[] = [
                'type' => 'Issue',
                'title' => $issue->title,
                'subtitle' => 'Status: '.($issue->status?->value ?? $issue->status),
                'icon' => 'heroicon-o-bug-ant',
                'url' => url('/admin/project-hub'.($issue->project_id ? '?project='.$issue->project_id : '')),
            ];
        }

        $conversations = \Modules\Communication\Models\Conversation::whereHas('customer', function ($q) use ($searchQuery) {
            $q->where('phone', 'like', $searchQuery)
                ->orWhere('name', 'like', $searchQuery);
        })->take(2)->get();
        foreach ($conversations as $conversation) {
            $this->results[] = [
                'type' => 'Conversation',
                'title' => $conversation->customer?->name ?? 'WhatsApp Thread',
                'subtitle' => $conversation->customer?->phone ?? 'Unknown',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => url('/admin/conversation-center'),
            ];
        }
    }

    public function render()
    {
        return view('livewire.global-command-palette');
    }
}
