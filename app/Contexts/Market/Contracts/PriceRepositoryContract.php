<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Models\Price;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface PriceRepositoryContract
{
    public function latestForAsset(int $id): ?Price;

    /**
     * Last known close of each asset, keyed by asset. Assets without any price are absent.
     *
     * Spares the callers a query per position: the overview and the sector breakdown both walk
     * the whole portfolio to value it.
     *
     * @param  array<int>  $ids
     * @return array<int, float>
     */
    public function latestClosesForAssets(array $ids): array;

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
     * Closing prices of several assets since a date, ordered by date, keyed by asset.
     *
     * Reads raw rows rather than models: the callers plot the closes, and hydrating the whole
     * history of every asset costs more than the query itself.
     *
     * @param  array<int>  $ids
     * @return array<int, list<float>>
     */
    public function closesForAssetsSince(array $ids, Carbon $since): array;

    /**
     * Daily closes of several assets since a date, ordered by asset then date, dates included.
     *
     * Same reasoning as closesForAssetsSince(): the valuation reads tens of thousands of rows per
     * request and only needs three columns, so nothing is hydrated into a model.
     *
     * @param  array<int>  $ids
     * @return list<array{assetId: int, date: string, close: float}>
     */
    public function dailyClosesForAssetsSince(array $ids, Carbon $since): array;

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
