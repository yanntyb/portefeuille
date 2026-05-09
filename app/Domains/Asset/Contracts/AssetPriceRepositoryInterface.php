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
    public function forAssetSince(int $assetId, Carbon $since): Collection;

    public function save(AssetPrice $price): void;

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function getLatestDateForAssets(array $assetIds): array;

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function getEarliestDateForAssets(array $assetIds): array;

    /**
     * @param  array<int>  $assetIds
     * @return Collection<int, AssetPrice>
     */
    public function getForAssets(array $assetIds, Carbon $since): Collection;
}
