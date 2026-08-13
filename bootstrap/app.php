<?php

use App\Contexts\Market\Console\SyncSectorsCommand;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SyncSectorsCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        // TODO refacto DDD : la commande securities:fetch-prices a ete supprimee (ancien
        // app/Console/Commands) et le write-side prix du contexte Market n'existe pas encore
        // (repos en lecture seule). Re-cabler le sync quotidien des prix une fois construit.
        // Voir docs/refactor-status.md.
        // $schedule->command('securities:fetch-prices')->daily();
        $schedule->command('market:sync-sectors')->weekly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            // Only redirect truly unmatched URLs to the home page. When a route
            // did match but its controller deliberately aborted with 404 (e.g. an
            // unknown resource id), let the real 404 response through.
            if ($request->route() !== null) {
                return null;
            }

            return new RedirectResponse('/');
        });
    })->create();
