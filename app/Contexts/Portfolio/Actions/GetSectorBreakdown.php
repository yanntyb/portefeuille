<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Services\SectorSplitter;
use Illuminate\Support\Collection;

class GetSectorBreakdown
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private SectorRepositoryContract $sectors,
        private SectorSplitter $splitter,
    ) {}

    /**
     * Break the portfolio market value down by sector. Assets without any known
     * sector fall into Sector::Other.
     *
     * Sans `$classes`, tout le portefeuille ; avec, les seules expositions demandées, les parts
     * étant alors celles de ce sous-ensemble — le partage se lit dans `AssetClass`, comme partout.
     *
     * @param  ?list<AssetClass>  $classes
     * @return list<AllocationSliceData>
     */
    public function __invoke(User $user, ?array $classes = null): array
    {
        $holdings = Holding::query()
            ->where('user_id', $user->id)
            ->when($classes !== null, fn ($query) => $query->whereHas(
                'asset',
                fn ($assets) => $assets->whereIn(
                    'asset_class',
                    array_map(fn (AssetClass $class): string => $class->value, $classes),
                ),
            ))
            ->get();

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        /** @var array<int, float> $valueByAsset */
        $valueByAsset = [];

        foreach ($holdings as $holding) {
            $assetId = (int) $holding->asset_id;
            $close = $lastPrices[$assetId] ?? null;

            if ($close === null) {
                continue;
            }

            $valueByAsset[$assetId] = ($valueByAsset[$assetId] ?? 0.0) + (float) $holding->quantity * $close;
        }

        if ($valueByAsset === []) {
            return [];
        }

        $slices = $this->splitter->split($valueByAsset, $this->weightsFor(array_keys($valueByAsset)), Sector::Other->value);

        return array_map(fn (array $slice): AllocationSliceData => new AllocationSliceData(
            label: Sector::from($slice['sector'])->getLabel(),
            value: $slice['value'],
            pct: $slice['pct'],
            color: Sector::from($slice['sector'])->getColor(),
        ), $slices);
    }

    /**
     * @param  list<int>  $assetIds
     * @return array<int, array<string, float>>
     */
    private function weightsFor(array $assetIds): array
    {
        return $this->sectors
            ->forAssets($assetIds)
            ->groupBy('asset_id')
            ->map(function (Collection $allocations): array {
                $weights = [];

                /**
                 * Un actif n'a qu'une ligne par secteur (`unique(asset_id, sector)`) ;
                 * l'accumulation est une garde, pas un cas réel.
                 */
                foreach ($allocations as $allocation) {
                    /** @var SectorAllocation $allocation */
                    $key = $allocation->sector->value;
                    $weights[$key] = ($weights[$key] ?? 0.0) + (float) $allocation->weight;
                }

                return $weights;
            })
            ->all();
    }
}
