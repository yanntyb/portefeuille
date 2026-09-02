<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\PortfolioView\Datas\AssetValuationData;
use App\Contexts\PortfolioView\Datas\DrawdownData;
use App\Contexts\PortfolioView\Datas\EvolutionData;
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
     * L'évolution détaillée par actif du périmètre : une exposition comme une enveloppe, puisque
     * `BuildEvolutionSeries` découpe une enveloppe avant son cache. Le total se somme côté page,
     * la valorisation ne portant pas les espèces.
     */
    public function evolutionFor(int $userId, HoldingScope $scope): EvolutionData;

    /** @return list<PerformanceLineData> */
    public function assetPerformancesFor(int $userId, int $assetId): array;

    public function assetSeriesFor(int $userId, int $assetId): AssetValuationData;

    /** La perte maximale vécue par un périmètre, et celle en cours. */
    public function drawdownFor(int $userId, HoldingScope $scope): DrawdownData;
}
