<?php

namespace App\Domains\Asset\Contracts;

use App\Domains\Asset\Models\AssetPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface AssetPriceRepositoryInterface
{
    public function findLatestForAsset(int $assetId): ?AssetPrice;

    public function findForAssetOnDate(int $assetId, Carbon $date): ?AssetPrice;

    /** @return Collection<int, AssetPrice> */
    public function forAssetSince(int $id, Carbon $since): Collection;

    public function save(AssetPrice $price): void;

    /**
     * @param  array<int>  $ids
     * @return Collection<int, AssetPrice>
     */
    public function getForAssets(array $ids, Carbon $since): Collection;

    /**
     * @param  array<int>  $ids
     * @return array<int>
     */
    public function filterAssetIdsHavingPriceSince(array $ids, Carbon $since): array;
}
