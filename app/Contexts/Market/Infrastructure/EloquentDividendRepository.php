<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Support\Carbon;

class EloquentDividendRepository implements DividendRepositoryContract
{
    public function latestForAsset(int $assetId): ?Dividend
    {
        return Dividend::query()
            ->where('asset_id', $assetId)
            ->orderByDesc('ex_date')
            ->first();
    }

    /**
     * @param  array<int>  $assetIds
     * @return list<array{assetId: int, exDate: string, amountPerShare: float}>
     */
    public function forAssets(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return Dividend::query()
            ->whereIn('asset_id', $assetIds)
            ->orderBy('asset_id')
            ->orderBy('ex_date')
            ->toBase()
            ->get(['asset_id', 'ex_date', 'amount_per_share'])
            ->map(fn (object $row): array => [
                'assetId' => (int) $row->asset_id,
                'exDate' => Carbon::parse($row->ex_date)->format('Y-m-d'),
                'amountPerShare' => (float) $row->amount_per_share,
            ])
            ->all();
    }

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereNotNull('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<int, DividendData>  $dividends
     */
    public function upsertForAsset(int $assetId, array $dividends): int
    {
        if ($dividends === []) {
            return 0;
        }

        $rows = array_map(fn (DividendData $dividend): array => [
            'asset_id' => $assetId,
            'ex_date' => $dividend->exDate,
            'amount_per_share' => $dividend->amountPerShare,
        ], $dividends);

        Dividend::query()->upsert($rows, ['asset_id', 'ex_date'], ['amount_per_share']);

        return count($rows);
    }
}
