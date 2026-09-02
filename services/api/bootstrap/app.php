<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The host system owns access control. This API intentionally has no auth middleware.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Public geometry/domain exceptions are mapped explicitly by their handlers.
    })
    ->create();
