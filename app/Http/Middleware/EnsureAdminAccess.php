<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->canAccessAdmin()) {
            if ($user->canAccessWorkspace()) {
                // Preserve deep links from emails/search: send employees to the
                // workspace copy of task-detail instead of dumping them on home.
                if ($request->routeIs('task-detail') && $request->route('record')) {
                    return redirect()->route('workspace.task-detail', $request->route('record'));
                }

                return redirect()->route('workspace.dashboard');
            }

            abort(403, 'You do not have access to the admin panel.');
        }

        return $next($request);
    }
}
