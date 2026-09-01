<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\InstrumentMetaData;
use App\Contexts\MarketView\Datas\InstrumentSummaryData;
use App\Contexts\MarketView\Datas\PriceHistoryData;
use App\Contexts\MarketView\Datas\SectorWeightData;
use Illuminate\Support\Carbon;

interface MarketDataPort
{
    /** @return list<InstrumentSummaryData> */
    public function listInstruments(): array;

    /**
     * Tous les instruments d'une exposition, détenus ou non, rangés par nom : le catalogue de la
     * poche, là où `listInstruments()` ne connaît aucun partage.
     *
     * @return list<InstrumentSummaryData>
     */
    public function instrumentsOfClass(AssetClass $class): array;

    /**
     * Dernier cours connu de plusieurs actifs, indexé par actif. Un actif sans aucun cours est
     * absent de la carte. Épargne au catalogue une requête par ligne.
     *
     * @param  list<int>  $assetIds
     * @return array<int, float>
     */
    public function latestPricesFor(array $assetIds): array;

    public function findInstrument(int $id): ?InstrumentMetaData;

    public function latestPrice(int $id): ?float;

    public function priceHistory(int $id, Carbon $since): PriceHistoryData;

    /**
     * Closing prices of several instruments since a date, ordered by date, keyed by instrument.
     * Instruments without any price in the window are absent from the map.
     *
     * @param  array<int>  $assetIds
     * @return array<int, list<float>>
     */
    public function closeSeriesSince(array $assetIds, Carbon $since): array;

    /** @return list<SectorWeightData> */
    public function sectors(int $id): array;

    /**
     * Parmi ces actifs, ceux qui portent l'une des expositions données. Le filtre descend ici et
     * non dans l'action : `MarketView` lit le marché par ses ports, jamais par ses modèles.
     *
     * @param  list<int>  $assetIds
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $assetIds, array $classes): array;
}
