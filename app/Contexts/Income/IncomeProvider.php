<?php

namespace App\Contexts\Income;

use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Ports\IncomeSourcePort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class IncomeProvider extends ServiceProvider
{
    /**
     * @param  list<class-string<IncomeSourcePort>>  $sources  origines de revenu à agréger
     */
    public static function registers(Application $app, array $sources): void
    {
        $app->tag($sources, 'income.sources');

        $app->bind(
            IncomeSourceRegistry::class,
            fn (Application $app): IncomeSourceRegistry => new IncomeSourceRegistry($app->tagged('income.sources')),
        );
    }
}
