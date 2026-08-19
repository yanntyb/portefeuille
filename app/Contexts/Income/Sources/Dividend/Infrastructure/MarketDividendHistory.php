<?php

namespace App\Contexts\Income\Sources\Dividend\Infrastructure;

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Market\Contracts\DividendRepositoryContract;
use Illuminate\Support\Carbon;

class MarketDividendHistory implements DividendHistoryPort
{
    public function __construct(private DividendRepositoryContract $dividends) {}

    /**
     * @param  array<int>  $assetIds
     * @return list<DividendRecordData>
     */
    public function forAssets(array $assetIds): array
    {
        return array_map(
            fn (array $row): DividendRecordData => new DividendRecordData(
                assetId: $row['assetId'],
                exDate: Carbon::parse($row['exDate']),
                amountPerShare: $row['amountPerShare'],
            ),
            $this->dividends->forAssets($assetIds),
        );
    }

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array
    {
        return $this->dividends->namesFor($assetIds);
    }
}
