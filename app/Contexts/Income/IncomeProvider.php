<?php

namespace App\Contexts\Income;

use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Ports\IncomeSourcePort;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class IncomeProvider extends ServiceProvider
{
    /**
     * @param  list<class-string<IncomeSourcePort>>  $sources  origines de revenu à agréger
     * @param  class-string<DividendHistoryPort>  $dividendHistory
     * @param  class-string<PositionHistoryPort>  $positionHistory
     */
    public static function registers(
        Application $app,
        array $sources,
        string $dividendHistory,
        string $positionHistory,
    ): void {
        $app->bind(DividendHistoryPort::class, $dividendHistory);
        $app->bind(PositionHistoryPort::class, $positionHistory);

        $app->tag($sources, 'income.sources');

        $app->bind(
            IncomeSourceRegistry::class,
            fn (Application $app): IncomeSourceRegistry => new IncomeSourceRegistry($app->tagged('income.sources')),
        );
    }
}
