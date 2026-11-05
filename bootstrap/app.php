<?php

use App\Foundation\Bootstrap\LoadConfiguration;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration as FrameworkLoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => RoleMiddleware::class]);
        $middleware->validateCsrfTokens(except: ['payments/midtrans/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

$app->bind(FrameworkLoadConfiguration::class, LoadConfiguration::class);

return $app;
