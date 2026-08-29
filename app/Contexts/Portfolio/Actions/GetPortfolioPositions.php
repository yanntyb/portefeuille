<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Services\HoldingValuator;
use App\Contexts\Portfolio\Services\PositionAggregator;
use Illuminate\Support\Collection;

/**
 * La position de chaque actif, enveloppes confondues et valorisée. Exposée par `Portfolio` parce
 * que `MarketView` et `Income` en avaient tous deux besoin et l'avaient tous deux réimplémentée.
 */
class GetPortfolioPositions
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private PositionAggregator $aggregate,
        private HoldingValuator $valuator,
    ) {}

    /** @return array<int, PositionLineData> */
    public function __invoke(int $userId): array
    {
        $holdings = Holding::query()->where('user_id', $userId)->get();

        if ($holdings->isEmpty()) {
            return [];
        }

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        return $holdings
            ->groupBy('asset_id')
            ->mapWithKeys(fn (Collection $rows, int|string $assetId): array => [
                (int) $assetId => $this->position((int) $assetId, $rows, $lastPrices[(int) $assetId] ?? null),
            ])
            ->all();
    }

    /** @param  Collection<int, Holding>  $rows */
    private function position(int $assetId, Collection $rows, ?float $lastPrice): PositionLineData
    {
        $aggregated = ($this->aggregate)($rows->map(fn (Holding $holding): array => [
            'quantity' => (float) $holding->quantity,
            'avgCost' => $holding->avg_cost !== null ? (float) $holding->avg_cost : null,
        ])->values()->all());

        $valued = $this->valuator->value($aggregated['quantity'], $aggregated['avgCost'], $lastPrice);

        return new PositionLineData(
            assetId: $assetId,
            quantity: $aggregated['quantity'],
            avgCost: $aggregated['avgCost'],
            lastPrice: $lastPrice,
            marketValue: $valued['marketValue'],
            gain: $valued['gain'],
            gainPct: $valued['gainPct'],
        );
    }
}
