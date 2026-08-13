<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Portfolio\Models\Holding;

class GetSectorBreakdown
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private SectorRepositoryContract $sectors,
    ) {}

    /**
     * Break the portfolio market value down by sector. Assets without any known
     * sector fall into Sector::Other.
     *
     * @return list<AllocationSliceData>
     */
    public function __invoke(User $user): array
    {
        $holdings = Holding::query()
            ->where('user_id', $user->id)
            ->get();

        /** @var array<int, float> $valueByAsset */
        $valueByAsset = [];
        $totalValue = 0.0;

        foreach ($holdings as $holding) {
            $price = $this->prices->latestForAsset($holding->asset_id);

            if ($price === null) {
                continue;
            }

            $marketValue = (float) $holding->quantity * (float) $price->close;
            $assetId = (int) $holding->asset_id;

            $valueByAsset[$assetId] = ($valueByAsset[$assetId] ?? 0.0) + $marketValue;
            $totalValue += $marketValue;
        }

        if ($valueByAsset === []) {
            return [];
        }

        $weightsByAsset = $this->sectors
            ->forAssets(array_keys($valueByAsset))
            ->groupBy('asset_id');

        /** @var array<string, float> $valueBySector */
        $valueBySector = [];

        foreach ($valueByAsset as $assetId => $value) {
            $weights = $weightsByAsset->get($assetId);
            $totalWeight = $weights?->sum(fn (SectorAllocation $allocation) => (float) $allocation->weight) ?? 0.0;

            if ($weights === null || $totalWeight <= 0.0) {
                $key = Sector::Other->value;
                $valueBySector[$key] = ($valueBySector[$key] ?? 0.0) + $value;

                continue;
            }

            foreach ($weights as $allocation) {
                $key = $allocation->sector->value;
                $share = (float) $allocation->weight / $totalWeight;
                $valueBySector[$key] = ($valueBySector[$key] ?? 0.0) + $value * $share;
            }
        }

        arsort($valueBySector);

        $slices = [];
        foreach ($valueBySector as $sectorValue => $value) {
            $sector = Sector::from($sectorValue);
            $slices[] = new AllocationSliceData(
                label: $sector->getLabel(),
                value: $value,
                pct: $totalValue > 0.0 ? $value / $totalValue * 100 : 0.0,
                color: $sector->getColor(),
            );
        }

        return $slices;
    }
}
