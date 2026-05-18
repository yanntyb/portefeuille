<?php

namespace App\Domains\Asset\Infrastructure\Eloquent;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Models\AssetPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class EloquentAssetPriceRepository implements AssetPriceRepositoryInterface
{
    public function latestForAsset(int $id): ?AssetPrice
    {
        return AssetPrice::query()
            ->where('asset_id', $id)
            ->orderByDesc('date')
            ->first();
    }

    public function forAssetOnDate(int $id, Carbon $date): ?AssetPrice
    {
        return AssetPrice::query()
            ->where('asset_id', $id)
            ->whereDate('date', $date)
            ->first();
    }

    public function forAssetSince(int $id, Carbon $since): Collection
    {
        return AssetPrice::query()
            ->where('asset_id', $id)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();
    }

    public function getForAssets(array $ids, Carbon $since): Collection
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $ids)
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
