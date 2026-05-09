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

    /** @return array<int, string> */
    public function getLatestDateForAssets(array $assetIds): array
    {
        return AssetPrice::query()
            ->selectRaw('asset_id, DATE(MAX(date)) as latest_date')
            ->whereIn('asset_id', $assetIds)
            ->groupBy('asset_id')
            ->pluck('latest_date', 'asset_id')
            ->all();
    }

    /** @return array<int, string> */
    public function getEarliestDateForAssets(array $assetIds): array
    {
        return AssetPrice::query()
            ->selectRaw('asset_id, DATE(MIN(date)) as earliest_date')
            ->whereIn('asset_id', $assetIds)
            ->groupBy('asset_id')
            ->pluck('earliest_date', 'asset_id')
            ->all();
    }

    public function getForAssets(array $assetIds, Carbon $since): Collection
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $assetIds)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();
    }

    public function getAssetIdsWithRecentPrice(array $assetIds, string $sinceDate): array
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $assetIds)
            ->where('date', '>=', $sinceDate)
            ->distinct()
            ->pluck('asset_id')
            ->all();
    }
}
