<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\PortfolioSummaryData;

/**
 * L'aperçu chiffré d'une exposition. Une exposition à la fois : la page liste n'en montre jamais
 * deux, et le portefeuille entier se lit depuis le tableau de bord, pas d'ici.
 */
interface PortfolioOverviewPort
{
    public function overviewFor(int $userId, AssetClass $exposure): PortfolioSummaryData;
}
