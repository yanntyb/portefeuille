<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Price;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class EloquentPriceRepository implements PriceRepositoryContract
{
    public function latestForAsset(int $id): ?Price
    {
        return Price::query()
            ->where('asset_id', $id)
            ->orderByDesc('date')
            ->first();
    }

    public function forAssetOnDate(int $id, Carbon $date): ?Price
    {
        return Price::query()
            ->where('asset_id', $id)
            ->whereDate('date', $date)
            ->first();
    }

    public function forAssetSince(int $id, Carbon $since): Collection
    {
        return Price::query()
            ->where('asset_id', $id)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();
    }

    public function forAssets(array $ids, Carbon $since): Collection
    {
        return Price::query()
            ->whereIn('asset_id', $ids)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();
    }

    public function filterAssetIdsHavingPriceSince(array $ids, Carbon $since): array
    {
        return Price::query()
            ->whereIn('asset_id', $ids)
            ->where('date', '>=', $since)
            ->distinct()
            ->pluck('asset_id')
            ->all();
    }
}
