<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Routes de compatibilite avec l'ancien backend PHP (php-mvst/app/).
            // Chargees hors des groupes "web" et "api" : ni session, ni CSRF, ni
            // limitation de debit, pour reproduire a l'identique le comportement
            // des fichiers .php d'origine (aucun de ces mecanismes n'existait).
            require base_path('routes/legacy.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
