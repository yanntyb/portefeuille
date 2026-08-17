<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\PriceData;
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

    /**
     * @param  array<int>  $ids
     * @return array<int, float>
     */
    public function latestClosesForAssets(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $table = (new Price)->getTable();

        /** Le dernier jour coté de chaque actif, joint à sa ligne : une requête au lieu d'une par position. */
        $latestDates = Price::query()
            ->toBase()
            ->select('asset_id')
            ->selectRaw('max(date) as date')
            ->whereIn('asset_id', $ids)
            ->groupBy('asset_id');

        $rows = Price::query()
            ->toBase()
            ->from($table.' as prices')
            ->joinSub($latestDates, 'latest', function ($join): void {
                $join->on('prices.asset_id', '=', 'latest.asset_id')
                    ->on('prices.date', '=', 'latest.date');
            })
            ->select('prices.asset_id', 'prices.close')
            ->get();

        $closes = [];

        foreach ($rows as $row) {
            $closes[(int) $row->asset_id] = (float) $row->close;
        }

        return $closes;
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

    /**
     * @param  array<int>  $ids
     * @return array<int, list<float>>
     */
    public function closesForAssetsSince(array $ids, Carbon $since): array
    {
        if ($ids === []) {
            return [];
        }

        $series = [];

        $rows = Price::query()
            ->toBase()
            ->select('asset_id', 'close')
            ->whereIn('asset_id', $ids)
            ->where('date', '>=', $since)
            ->orderBy('asset_id')
            ->orderBy('date')
            ->get();

        foreach ($rows as $row) {
            $series[(int) $row->asset_id][] = (float) $row->close;
        }

        return $series;
    }

    /**
     * @param  array<int>  $ids
     * @return list<array{assetId: int, date: string, close: float}>
     */
    public function dailyClosesForAssetsSince(array $ids, Carbon $since): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = Price::query()
            ->toBase()
            ->select('asset_id', 'date', 'close')
            ->whereIn('asset_id', $ids)
            ->where('date', '>=', $since)
            ->orderBy('asset_id')
            ->orderBy('date')
            ->get();

        /** La date est lue brute : le cast Eloquent est court-circuité, seul le jour nous intéresse. */
        return $rows->map(fn (object $row): array => [
            'assetId' => (int) $row->asset_id,
            'date' => substr((string) $row->date, 0, 10),
            'close' => (float) $row->close,
        ])->all();
    }

    /**
     * Insert or update the daily prices of an asset.
     *
     * The date is reformatted rather than passed through: `upsert()` writes raw values and
     * bypasses Eloquent's casting, so the string must match what Eloquent itself writes for the
     * `date` cast ('Y-m-d H:i:s'). Under SQLite's manifest typing '2026-01-03' and
     * '2026-01-03 00:00:00' are distinct keys, so reverting to `$price->date` would silently stop
     * `unique(['asset_id', 'date'])` from firing and insert duplicate rows for the same day.
     *
     * @param  array<int, PriceData>  $prices
     * @return int number of rows submitted to the database — the whole batch, since an upsert
     *             cannot tell an insert from an unchanged update
     */
    public function upsertForAsset(int $assetId, array $prices): int
    {
        if ($prices === []) {
            return 0;
        }

        $rows = array_map(fn (PriceData $price): array => [
            'asset_id' => $assetId,
            'date' => Carbon::parse($price->date)->format('Y-m-d H:i:s'),
            'open' => $price->open,
            'high' => $price->high,
            'low' => $price->low,
            'close' => $price->close,
            'volume' => $price->volume,
        ], $prices);

        $this->deleteBareDateRows($assetId, $rows);

        Price::query()->upsert($rows, ['asset_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);

        return count($rows);
    }

    /**
     * Drop the rows of the targeted days whose date was stored in the bare 'Y-m-d' form.
     *
     * Such rows come from writes that skipped Eloquent — the sync of the previous architecture
     * used `insertOrIgnore()` with the raw Python date, and any query-builder insert does the
     * same. SQLite compares them as text, so they never collide with the canonical
     * 'Y-m-d H:i:s' key and the upsert below would add a second row for the same day. The extra
     * inequality keeps this a no-op on drivers with a real date type, where both spellings
     * compare equal.
     *
     * @param  array<int, array{asset_id: int, date: string}>  $rows
     */
    private function deleteBareDateRows(int $assetId, array $rows): void
    {
        $canonicalDates = array_column($rows, 'date');

        Price::query()
            ->where('asset_id', $assetId)
            ->whereIn('date', array_map(
                fn (string $date): string => substr($date, 0, 10),
                $canonicalDates,
            ))
            ->whereNotIn('date', $canonicalDates)
            ->delete();
    }
}
