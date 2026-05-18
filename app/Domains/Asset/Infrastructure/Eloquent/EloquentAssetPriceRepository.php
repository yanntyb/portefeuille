<?php

namespace App\Domains\Asset\Infrastructure\Eloquent;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Models\AssetPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class EloquentAssetPriceRepository implements AssetPriceRepositoryInterface
{
    public function findLatestForAsset(int $assetId): ?AssetPrice
    {
        return AssetPrice::query()
            ->where('asset_id', $assetId)
            ->orderByDesc('date')
            ->first();
    }

    public function findForAssetOnDate(int $assetId, Carbon $date): ?AssetPrice
    {
        return AssetPrice::query()
            ->where('asset_id', $assetId)
            ->whereDate('date', $date)
            ->first();
    }

    public function forAssetSince(int $assetId, Carbon $since): Collection
    {
        return AssetPrice::query()
            ->where('asset_id', $assetId)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();
    }

    public function save(AssetPrice $price): void
    {
        $price->save();
    }

    public function getForAssets(array $assetIds, Carbon $since): Collection
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $assetIds)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();
    }

    public function filterAssetIdsHavingPriceSince(array $ids, Carbon $since): array
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $ids)
            ->where('date', '>=', $since)
            ->distinct()
            ->pluck('asset_id')
            ->all();
    }

    public function getForSecurities(array $assetIds): Collection
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $assetIds)
            ->orderBy('date')
            ->get();
    }
}
