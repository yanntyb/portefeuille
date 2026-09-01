<?php

namespace App\Contexts\MarketView\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\CatalogLineData;
use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Datas\InstrumentSummaryData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\MarketDataPort;

class GetClassCatalog
{
    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
    ) {}

    /**
     * Le catalogue d'une exposition : tous ses instruments, dans l'ordre alphabétique que rend le
     * port. Les détenus ne remontent pas en tête — c'est un ordre de recherche, pas de portefeuille.
     *
     * @return list<CatalogLineData>
     */
    public function __invoke(int $userId, AssetClass $class): array
    {
        $instruments = $this->market->instrumentsOfClass($class);

        $assetIds = array_map(
            fn (InstrumentSummaryData $instrument): int => $instrument->id,
            $instruments,
        );

        $prices = $this->market->latestPricesFor($assetIds);

        /** @var array<int, float> $quantities une position par actif, toutes enveloppes confondues */
        $quantities = [];

        foreach ($this->holdings->holdingsFor($userId) as $holding) {
            /** @var HoldingSnapshotData $holding */
            $quantities[$holding->assetId] = $holding->quantity;
        }

        return array_map(
            fn (InstrumentSummaryData $instrument): CatalogLineData => $this->toLine(
                $instrument,
                $prices[$instrument->id] ?? null,
                $quantities[$instrument->id] ?? null,
            ),
            $instruments,
        );
    }

    private function toLine(
        InstrumentSummaryData $instrument,
        ?float $lastPrice,
        ?float $quantity,
    ): CatalogLineData {
        return new CatalogLineData(
            id: $instrument->id,
            name: $instrument->name,
            ticker: $instrument->ticker,
            isin: $instrument->isin,
            type: $instrument->type,
            lastPrice: $lastPrice,
            held: $quantity !== null,
            quantity: $quantity,
            marketValue: $quantity !== null && $lastPrice !== null ? $quantity * $lastPrice : null,
        );
    }
}
