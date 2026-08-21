<?php

namespace App\Contexts\Wealth;

use App\Contexts\Wealth\Ports\HoldingsPort;
use App\Contexts\Wealth\Ports\IncomePort;
use App\Contexts\Wealth\Ports\RealEstatePort;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WealthProvider extends ServiceProvider
{
    /**
     * @param  class-string<HoldingsPort>  $holdings
     * @param  class-string<SecuritiesSeriesPort>  $securitiesSeries
     * @param  class-string<RealEstatePort>  $realEstate
     * @param  class-string<IncomePort>  $income
     */
    public static function registers(
        Application $app,
        string $holdings,
        string $securitiesSeries,
        string $realEstate,
        string $income,
    ): void {
        $app->bind(HoldingsPort::class, $holdings);
        $app->bind(SecuritiesSeriesPort::class, $securitiesSeries);
        $app->bind(IncomePort::class, $income);

        /**
         * Le résumé, la série et les revenus interrogent tous les trois le parc immobilier dans la
         * même requête : `scoped()` évite de refaire trois fois les mêmes lectures.
         */
        $app->scoped(RealEstatePort::class, $realEstate);
    }
}
