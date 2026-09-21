<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Generic error renderer for all HTTP exceptions
        $renderError = static function (Request $request, int $status) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Error occurred.',
                    'status' => $status,
                ], $status);
            }

            return Inertia::render('Error', [
                'status' => $status,
            ])->toResponse($request)->setStatusCode($status);
        };

        // Handle all HTTP exceptions (404, 419, 500, 503, etc.)
        $exceptions->renderable(function (HttpException $exception, Request $request) use ($renderError) {
            $status = $exception->getStatusCode();
            return $renderError($request, $status);
        });

        // Handle model not found (returns 404)
        $exceptions->renderable(function (ModelNotFoundException $exception, Request $request) use ($renderError) {
            return $renderError($request, 404);
        });
    })
    ->create();
