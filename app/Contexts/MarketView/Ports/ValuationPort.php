<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\AssetValuationData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\PerformanceLineData;

/**
 * Ce que la valorisation apprend au volet marché, aux deux échelles qu'il affiche : une exposition
 * sur la page liste, un actif sur sa fiche.
 *
 * Ni la profondeur d'historique ni le pas ne traversent ce port : ce sont des décisions de rendu
 * de ces pages, et les faire passer obligerait l'appelant à importer un enum du contexte voisin —
 * soit troquer un appel direct contre une dépendance de type.
 */
interface ValuationPort
{
    /** @return list<PerformanceLineData> */
    public function performancesFor(int $userId, AssetClass $exposure): array;

    public function evolutionFor(int $userId, AssetClass $exposure): EvolutionData;

    /** @return list<PerformanceLineData> */
    public function assetPerformancesFor(int $userId, int $assetId): array;

    public function assetSeriesFor(int $userId, int $assetId): AssetValuationData;
}
