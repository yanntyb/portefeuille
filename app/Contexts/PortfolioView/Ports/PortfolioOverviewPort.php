<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\PortfolioView\Datas\AnalysisData;
use App\Contexts\PortfolioView\Datas\ClassSliceData;
use App\Contexts\PortfolioView\Datas\PortfolioSummaryData;
use App\Contexts\PortfolioView\Datas\PositionData;

/**
 * L'aperçu chiffré d'un périmètre : une exposition pour la page liste, une enveloppe pour la sienne.
 * Un périmètre à la fois — le portefeuille entier se lit depuis le tableau de bord, pas d'ici.
 */
interface PortfolioOverviewPort
{
    public function overviewFor(int $userId, HoldingScope $scope): PortfolioSummaryData;

    /**
     * Le périmètre ventilé par classe d'actif, la plus grosse part en tête. Vide quand il ne vaut
     * rien : diviser par zéro donnerait des parts infinies, et une part nulle sur chaque classe
     * n'apprendrait rien.
     *
     * @return list<ClassSliceData>
     */
    public function classBreakdownFor(int $userId, HoldingScope $scope): array;

    /** La position d'un actif, enveloppes confondues et valorisée. Nulle si l'actif n'est pas détenu. */
    public function positionFor(int $userId, int $assetId): ?PositionData;

    /** Les analyses d'un périmètre : ce qu'il concentre, et ce qui le fait avancer. */
    public function analysisFor(int $userId, HoldingScope $scope): AnalysisData;
}
