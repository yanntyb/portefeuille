<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\AnalysisData;
use App\Contexts\PortfolioView\Datas\PortfolioSummaryData;
use App\Contexts\PortfolioView\Datas\PositionData;

/**
 * L'aperçu chiffré d'une exposition. Une exposition à la fois : la page liste n'en montre jamais
 * deux, et le portefeuille entier se lit depuis le tableau de bord, pas d'ici.
 */
interface PortfolioOverviewPort
{
    public function overviewFor(int $userId, AssetClass $exposure): PortfolioSummaryData;

    /** La position d'un actif, enveloppes confondues et valorisée. Nulle si l'actif n'est pas détenu. */
    public function positionFor(int $userId, int $assetId): ?PositionData;

    /** Les analyses d'une exposition : ce qu'elle concentre, et ce qui la fait avancer. */
    public function analysisFor(int $userId, AssetClass $exposure): AnalysisData;
}
