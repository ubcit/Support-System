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

        if (! $user->canAccessWorkspace()) {
            if ($user->canAccessAdmin()) {
                return redirect()->route('dashboard');
            }

            abort(403, 'You do not have an employee workspace profile.');
        }

        return $next($request);
    }
}
