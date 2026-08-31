<?php

use App\Contexts\Identity\Http\AuthenticateDefaultUser;
use App\Contexts\Market\Console\SyncCommand;
use App\Contexts\Market\Console\SyncDividendsCommand;
use App\Contexts\Market\Console\SyncPricesCommand;
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
        SyncCommand::class,
        SyncDividendsCommand::class,
        SyncPricesCommand::class,
        SyncSectorsCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('market:sync-prices')
            ->dailyAt('23:30')
            ->appendOutputTo(storage_path('logs/market-sync-prices.log'));
        $schedule->command('market:sync-sectors')->weekly();

        /**
         * Hebdomadaire et non quotidien : un détachement est un évènement rare, et le
         * rattrapage repart toujours du dernier `ex_date` connu. L'horaire suit celui des
         * cours pour ne pas croiser deux process Python.
         */
        $schedule->command('market:sync-dividends')
            ->weeklyOn(6, '23:45')
            ->appendOutputTo(storage_path('logs/market-sync-dividends.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            /** Avant Inertia : un futur `share(['auth' => …])` doit voir l'utilisateur connecté. */
            AuthenticateDefaultUser::class,
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
