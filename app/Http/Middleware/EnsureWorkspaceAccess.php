<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->canAccessAdmin()) {
            return $this->redirectAdminToMatchingRoute($request);
        }

        if (! $user->canAccessWorkspace()) {
            abort(403, 'You do not have an employee workspace profile.');
        }

        return $next($request);
    }

    protected function redirectAdminToMatchingRoute(Request $request): Response
    {
        $path = '/'.ltrim($request->path(), '/');
        $query = $request->query();

        if (preg_match('#^/workspace/tasks/([^/]+)$#', $path, $matches)) {
            return redirect()->route('task-detail', array_merge(['record' => $matches[1]], $query));
        }

        if ($path === '/workspace/my-tasks' || str_starts_with($path, '/workspace/my-tasks/')) {
            return redirect()->route('task-dashboard', $query);
        }

        if ($path === '/workspace/profile' || str_starts_with($path, '/workspace/profile/')) {
            return redirect()->route('profile', $query);
        }

        return redirect()->route('dashboard', $query);
    }
}
