<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\AssetValuationData;
use App\Contexts\PortfolioView\Datas\DrawdownData;
use App\Contexts\PortfolioView\Datas\EvolutionData;
use App\Contexts\PortfolioView\Datas\ExposureSeriesData;
use App\Contexts\PortfolioView\Datas\PerformanceLineData;

/**
 * Ce que la valorisation apprend au volet portefeuille, aux deux échelles qu'il affiche : une exposition
 * sur la page liste, un actif sur sa fiche.
 *
 * Ni la profondeur d'historique ni le pas ne traversent ce port : ce sont des décisions de rendu
 * de ces pages, et les faire passer obligerait l'appelant à importer un enum du contexte voisin —
 * soit troquer un appel direct contre une dépendance de type.
 */
interface ValuationPort
{
    /** @return list<PerformanceLineData> */
    public function performancesFor(int $userId, HoldingScope $scope): array;

    /**
     * L'évolution détaillée par actif. Elle prend une exposition et non un périmètre, à dessein :
     * `BuildEvolutionSeries` filtre ses séries par actif APRÈS son cache, et ne sait donc pas
     * découper une enveloppe — une signature qui l'accepterait mentirait. Une enveloppe lit
     * `seriesFor()`, qui la rend en bloc.
     */
    public function evolutionFor(int $userId, AssetClass $exposure): EvolutionData;

    /** La valeur d'un périmètre dans le temps, comparée à ce qui y a été mis. */
    public function seriesFor(int $userId, HoldingScope $scope): ExposureSeriesData;

    /** @return list<PerformanceLineData> */
    public function assetPerformancesFor(int $userId, int $assetId): array;

    public function assetSeriesFor(int $userId, int $assetId): AssetValuationData;

    /** La perte maximale vécue par un périmètre, et celle en cours. */
    public function drawdownFor(int $userId, HoldingScope $scope): DrawdownData;
}
