<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.access' => \App\Http\Middleware\JWTAccessMiddleware::class,
            'json.response' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Apply JSON response middleware to all API routes
        $middleware->api([
            'json.response',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions with standardized JSON responses
        $exceptions->render(function (Throwable $e, $request) {
            // Only handle API requests
            if ($request->is('api/*')) {
                return \App\Exceptions\ApiExceptionHandler::handle($e, $request);
            }
        });
    })->create();
