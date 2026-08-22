<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminAccess::class,
            'workspace' => \App\Http\Middleware\EnsureWorkspaceAccess::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            return $user ? route($user->preferredHomeRoute()) : route('dashboard');
        });

        // The whole /api/v1/* surface previously had zero rate limiting.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! $request->is('api/*') && in_array($status, [419, 500], true) && ! app()->hasDebugModeEnabled()) {
                if ($status === 500) {
                    \Illuminate\Support\Facades\Log::error($e->getMessage(), [
                        'exception' => $e,
                        'url' => $request->fullUrl(),
                    ]);
                }

                return response()->view("errors.{$status}", [], $status);
            }

            return $response;
        });
    })->create();
