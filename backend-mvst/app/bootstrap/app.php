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
        // gestionImages.php est le seul endpoint du projet lu en multipart/form-data
        // (via $request->input(), pas de JSON brut possible pour un upload de
        // fichier). Sans cette exclusion, un champ vide ("titre" par exemple)
        // serait converti en NULL par ce middleware global avant meme d'atteindre
        // le controleur, ce qui violerait a tort une contrainte NOT NULL que le PHP
        // source ne viole jamais (confirme par test, voir A_REVOIR.md). Tous les
        // autres endpoints du projet lisent le corps JSON brut via getContent() et
        // ne sont donc jamais affectes par ce middleware.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request) => $request->is('gestionImages.php'),
        ]);
        $middleware->trimStrings(except: [
            fn (Request $request) => $request->is('gestionImages.php'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
