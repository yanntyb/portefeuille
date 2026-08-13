<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Database\Eloquent\Collection;

interface SectorRepositoryContract
{
    /**
     * @param  array<int>  $ids
     * @return Collection<int, SectorAllocation>
     */
    public function forAssets(array $ids): Collection;

    /**
     * Replace every sector allocation of an asset by the given list.
     *
     * @param  array<int, SectorAllocationData>  $allocations
     */
    public function replaceForAsset(int $assetId, array $allocations): void;
}
