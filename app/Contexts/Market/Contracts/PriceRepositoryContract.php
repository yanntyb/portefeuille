<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Models\Price;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface PriceRepositoryContract
{
    public function latestForAsset(int $id): ?Price;

    public function forAssetOnDate(int $id, Carbon $date): ?Price;

    /** @return Collection<int, Price> */
    public function forAssetSince(int $id, Carbon $since): Collection;

    /**
     * @param  array<int>  $ids
     * @return Collection<int, Price>
     */
    public function forAssets(array $ids, Carbon $since): Collection;

    /**
     * @param  array<int>  $ids
     * @return array<int>
     */
    public function filterAssetIdsHavingPriceSince(array $ids, Carbon $since): array;

    /**
     * Insert or update the daily prices of an asset.
     *
     * Rows are matched on (asset_id, date) and existing values are overwritten:
     * the provider revises past closes.
     *
     * @param  array<int, PriceData>  $prices
     * @return int number of rows submitted to the database — an upsert cannot report how many of
     *             them were inserts, so a resumed asset counts the re-sent boundary day too
     */
    public function upsertForAsset(int $assetId, array $prices): int;
}
