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
    /**
     * Les positions de chaque utilisateur, pour la durée de la requête. `holdingFor()` et
     * `positionFor()` appellent cette action une fois par position détenue lors de la
     * construction de l'instantané ; sans mémoïsation, chacun de ces appels relirait tout le
     * portefeuille et tous les derniers cours de l'utilisateur, sur le modèle de
     * `GetPortfolioOverview`.
     *
     * @var array<int, array<int, PositionLineData>>
     */
    private array $positionsByUser = [];

    public function __construct(
        private PriceRepositoryContract $prices,
        private PositionAggregator $aggregate,
        private HoldingValuator $valuator,
        private GetRealizedGains $realizedGains,
    ) {}

    /** @return array<int, PositionLineData> */
    public function __invoke(int $userId): array
    {
        return $this->positionsByUser[$userId] ??= $this->readPositions($userId);
    }

    /** @return array<int, PositionLineData> */
    private function readPositions(int $userId): array
    {
        $holdings = Holding::query()->where('user_id', $userId)->get();

        if ($holdings->isEmpty()) {
            return [];
        }

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        $realized = ($this->realizedGains)($userId);

        return $holdings
            ->groupBy('asset_id')
            ->mapWithKeys(fn (Collection $rows, int|string $assetId): array => [
                (int) $assetId => $this->position(
                    (int) $assetId,
                    $rows,
                    $lastPrices[(int) $assetId] ?? null,
                    $realized[(int) $assetId] ?? 0.0,
                ),
            ])
            ->all();
    }

    /** @param  Collection<int, Holding>  $rows */
    private function position(int $assetId, Collection $rows, ?float $lastPrice, float $realizedGain): PositionLineData
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
            marketValue: $valued['marketValue'],
            gain: $valued['gain'],
            gainPct: $valued['gainPct'],
            realizedGain: $realizedGain,
        );
    }
}
