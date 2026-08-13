<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Database\Eloquent\Collection;

class EloquentSectorRepository implements SectorRepositoryContract
{
    public function forAssets(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return SectorAllocation::query()
            ->whereIn('asset_id', $ids)
            ->orderByDesc('weight')
            ->get();
    }

    /**
     * @param  array<int, SectorAllocationData>  $allocations
     */
    public function replaceForAsset(int $assetId, array $allocations): void
    {
        SectorAllocation::query()->getConnection()->transaction(function () use ($assetId, $allocations): void {
            SectorAllocation::query()->where('asset_id', $assetId)->delete();

            foreach ($allocations as $allocation) {
                SectorAllocation::query()->create([
                    'asset_id' => $assetId,
                    'sector' => $allocation->sector,
                    'weight' => $allocation->weight,
                ]);
            }
        });
    }
}
