<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\PortfolioView\Datas\CatalogLineData;
use App\Contexts\PortfolioView\Datas\InstrumentSummaryData;

class GetClassCatalog
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
    ) {}

    /**
     * Le catalogue d'une exposition : tous ses instruments, dans l'ordre alphabétique. Les détenus
     * ne remontent pas en tête — c'est un ordre de recherche, pas de portefeuille.
     *
     * @return list<CatalogLineData>
     */
    public function __invoke(int $userId, AssetClass $class): array
    {
        $instruments = $this->instrumentsOf($class);
        $assetIds = array_map(
            fn (InstrumentSummaryData $instrument): int => $instrument->id,
            $instruments,
        );
        $prices = $assetIds === [] ? [] : $this->prices->latestClosesForAssets($assetIds);

        /** @var array<int, float> $quantities une position par actif, toutes enveloppes confondues */
        $quantities = [];

        foreach (($this->positions)($userId) as $position) {
            $quantities[$position->assetId] = $position->quantity;
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

    /** @return list<InstrumentSummaryData> */
    private function instrumentsOf(AssetClass $class): array
    {
        return Instrument::query()
            ->where('asset_class', $class->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Instrument $instrument): InstrumentSummaryData => new InstrumentSummaryData(
                id: $instrument->id,
                name: (string) $instrument->name,
                ticker: $instrument->ticker,
                isin: $instrument->isin,
                type: $instrument->type,
            ))
            ->values()
            ->all();
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
