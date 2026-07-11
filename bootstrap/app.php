<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // TODO refacto DDD : les commandes securities:fetch-prices / securities:fetch-sectors
        // ont ete supprimees (ancien app/Console/Commands). Le contexte Market actuel n'a pas
        // encore de chemin d'ecriture (repos en lecture seule). Re-cabler le sync quotidien
        // une fois le write-side Market construit. Voir docs/refactor-status.md.
        // $schedule->command('securities:fetch-prices')->daily();
        // $schedule->command('securities:fetch-sectors')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e) {
            return new RedirectResponse('/');
        });
    })->create();
