<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'driver.portal' => \App\Http\Middleware\EnsureDriverPortalAccess::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'telegram/*',
        ]);
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if (str_contains($request->path(), 'driverportal')) {
                return route('driverportal.login', ['locale' => $request->route('locale') ?? app()->getLocale()]);
            }
            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
