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
         * Les trois actions du contexte (résumé, série, revenus) partagent une même instance de
         * l'adaptateur pour la durée de la requête : `scoped()` donne au port un unique cycle de
         * vie par requête plutôt que trois. Les lectures sous-jacentes ne sont pas mémoïsées pour
         * autant — chaque appel rejoue ses propres requêtes sur le parc immobilier.
         */
        $app->scoped(RealEstatePort::class, $realEstate);
    }
}
