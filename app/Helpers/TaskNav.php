<?php

namespace App\Helpers;

class TaskNav
{
    public const SESSION_KEY = 'task_nav.filters';

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function clean(array $filters): array
    {
        if (! empty($filters['completed']) && (filter_var($filters['completed'], FILTER_VALIDATE_BOOLEAN) || (int) $filters['completed'] === 1)) {
            $filters['completed'] = 1;
        } else {
            $filters['completed'] = null;
        }

        if (! empty($filters['trashed']) && (filter_var($filters['trashed'], FILTER_VALIDATE_BOOLEAN) || (int) $filters['trashed'] === 1)) {
            $filters['trashed'] = 1;
            // Trash is exclusive of completed / other queue filters.
            $filters['completed'] = null;
            $filters['queue'] = null;
        } else {
            $filters['trashed'] = null;
        }

        $query = [
            'view' => $filters['view'] ?? null,
            'project' => $filters['project'] ?? null,
            'due' => $filters['due'] ?? null,
            'scope' => $filters['scope'] ?? null,
            'completed' => $filters['completed'] ?? null,
            'trashed' => $filters['trashed'] ?? null,
            'priority' => $filters['priority'] ?? null,
            'assignee' => $filters['assignee'] ?? null,
            'status' => $filters['status'] ?? null,
            'queue' => $filters['queue'] ?? null,
            'create' => ! empty($filters['create']) ? 1 : null,
            'task' => $filters['task'] ?? null,
        ];

        return array_filter($query, function ($value, $key) {
            if ($value === null || $value === '' || $value === false) {
                return false;
            }

            if ($key === 'view' && $value === 'list') {
                return false;
            }

            if ($key === 'scope' && $value === 'all') {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(): array
    {
        return static::clean([
            'view' => request('view'),
            'project' => request('project'),
            'due' => request('due'),
            'scope' => request('scope'),
            'completed' => request()->has('completed') ? request('completed') : null,
            'trashed' => request()->has('trashed') ? request('trashed') : null,
            'priority' => request('priority'),
            'assignee' => request('assignee'),
            'status' => request('status'),
            'queue' => request('queue'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function remember(array $filters): void
    {
        session([self::SESSION_KEY => static::clean($filters)]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function remembered(): array
    {
        return static::clean(session(self::SESSION_KEY, []));
    }

    /**
     * Request filters if present, otherwise the last My Tasks visit.
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        $fromRequest = static::fromRequest();

        return $fromRequest !== [] ? $fromRequest : static::remembered();
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function dashboardUrl(?array $filters = null): string
    {
        $query = $filters ?? static::current();

        if (static::usesWorkspaceTaskRoutes()) {
            return route('workspace.employee', $query);
        }

        return route('task-dashboard', $query);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function dashboardUrlFor(?\App\Models\User $viewer, ?array $filters = null): string
    {
        $query = $filters ?? [];

        if (static::usesWorkspaceTaskRoutesFor($viewer)) {
            return route('workspace.employee', $query);
        }

        return route('task-dashboard', $query);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function detailUrl(int|string $taskId, ?array $filters = null): string
    {
        return static::detailUrlFor(auth()->user(), $taskId, $filters !== null ? $filters : static::current());
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function detailUrlFor(?\App\Models\User $viewer, int|string $taskId, ?array $filters = null): string
    {
        $query = $filters !== null ? static::clean($filters) : [];
        $url = static::usesWorkspaceTaskRoutesFor($viewer)
            ? route('workspace.task-detail', $taskId)
            : route('task-detail', $taskId);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Employees use /workspace/tasks/{id}; admin/boss/manager keep /admin/task-detail.
     */
    public static function usesWorkspaceTaskRoutes(): bool
    {
        return static::usesWorkspaceTaskRoutesFor(auth()->user());
    }

    public static function usesWorkspaceTaskRoutesFor(?\App\Models\User $user): bool
    {
        return $user
            && method_exists($user, 'canAccessAdmin')
            && ! $user->canAccessAdmin()
            && method_exists($user, 'canAccessWorkspace')
            && $user->canAccessWorkspace();
    }

    public static function workspaceUrl(?string $tab = null, ?int $taskId = null): string
    {
        $query = array_filter([
            'tab' => $tab && $tab !== 'queue' ? $tab : null,
            'task' => $taskId,
        ], fn ($value) => $value !== null && $value !== '');

        return route('workspace.employee', $query);
    }
}
