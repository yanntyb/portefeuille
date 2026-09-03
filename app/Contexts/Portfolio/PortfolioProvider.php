<?php

namespace App\Contexts\Portfolio;

use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Actions\GetRealizedGains;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Portfolio n'a pas de port à lier : il lit ses propres tables. Il mémoïse en revanche quatre
 * lectures par requête HTTP, une seule lecture du portefeuille servant toutes les expositions,
 * toutes les fiches et tous les recalculs d'une même requête.
 */
class PortfolioProvider extends ServiceProvider
{
    public static function registers(Application $app): void
    {
        /** Une lecture du portefeuille par requête : les classes d'actif la partagent. */
        $app->scoped(GetPortfolioOverview::class);

        /** Une lecture des positions par requête : PortfolioView et Income l'appellent une fois par position détenue. */
        $app->scoped(GetPortfolioPositions::class);

        /** Une lecture des ventes par requête : le gain réalisé se lit aux quatre expositions. */
        $app->scoped(GetRealizedGains::class);

        /** Une lecture des mouvements d'espèces par requête : RecomputeCashDeposits la relit à chaque transaction touchée. */
        $app->scoped(GetCashMovements::class);
    }
}
