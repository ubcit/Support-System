<?php

namespace Modules\Tasks\Services;

use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class GlobalSearchService
{
    public function search(string $query, int $limit = 10): array
    {
        if (trim($query) === '') {
            return [];
        }

        $term = "%{$query}%";

        $tasks = Task::where('title', 'like', $term)
            ->orWhere('description', 'like', $term)
            ->limit($limit)->get()
            ->map(fn ($t) => [
                'type' => 'task',
                'id' => $t->id,
                'uuid' => $t->uuid,
                'title' => $t->title,
                'subtitle' => "Task #{$t->id} • Priority: {$t->priority->value}",
                'url' => "/admin/task-detail/{$t->id}",
            ]);

        $projects = Project::where('name', 'like', $term)
            ->orWhere('description', 'like', $term)
            ->limit($limit)->get()
            ->map(fn ($p) => [
                'type' => 'project',
                'id' => $p->id,
                'uuid' => $p->uuid,
                'title' => $p->name,
                'subtitle' => "Project #{$p->id}",
                'url' => '/admin/project-hub?q='.urlencode($p->name),
            ]);

        $customers = Customer::where('name', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->limit($limit)->get()
            ->map(fn ($c) => [
                'type' => 'customer',
                'id' => $c->id,
                'uuid' => $c->uuid,
                'title' => $c->name,
                'subtitle' => "Customer • Phone: {$c->phone}",
                'url' => '/admin/customer-crm',
            ]);

        $employees = Employee::where('name', 'like', $term)
            ->orWhere('email', 'like', $term)
            ->limit($limit)->get()
            ->map(fn ($e) => [
                'type' => 'employee',
                'id' => $e->id,
                'uuid' => $e->uuid,
                'title' => $e->name,
                'subtitle' => "Employee • {$e->role}",
                'url' => '/admin/employee-management',
            ]);

        $issues = Issue::where('title', 'like', $term)
            ->orWhere('description', 'like', $term)
            ->limit($limit)->get()
            ->map(fn ($i) => [
                'type' => 'issue',
                'id' => $i->id,
                'uuid' => $i->uuid,
                'title' => $i->title,
                'subtitle' => "Issue #{$i->id}",
                'url' => '/admin/project-hub',
            ]);

        return [
            'query' => $query,
            'results' => [
                'tasks' => $tasks,
                'projects' => $projects,
                'customers' => $customers,
                'employees' => $employees,
                'issues' => $issues,
            ],
            'total_count' => $tasks->count() + $projects->count() + $customers->count() + $employees->count() + $issues->count(),
        ];
    }
}
