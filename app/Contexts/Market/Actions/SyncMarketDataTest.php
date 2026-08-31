<?php

use App\Contexts\Market\Actions\SyncMarketData;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Market\Ports\DividendFeedPort;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\SectorProviderPort;

it('syncs prices, sectors and dividends, in that order', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);

    $calls = [];

    $this->mock(PriceFeedPort::class, function ($mock) use (&$calls) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturnUsing(function () use (&$calls): array {
            $calls[] = 'prices';

            return ['AAPL' => [new PriceData(date: '2026-08-31', close: 10.0)]];
        });
    });

    $this->mock(SectorProviderPort::class, function ($mock) use (&$calls) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturnUsing(function () use (&$calls): array {
            $calls[] = 'sectors';

            return [new SectorAllocationData(sector: Sector::Technology, weight: 1.0)];
        });
    });

    $this->mock(DividendFeedPort::class, function ($mock) use (&$calls) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andReturnUsing(function () use (&$calls): array {
            $calls[] = 'dividends';

            return ['AAPL' => [new DividendData(exDate: '2026-08-20', amountPerShare: 0.5)]];
        });
    });

    $report = app(SyncMarketData::class)();

    /** L'ordre est la règle : trois process Python à la file, jamais deux de front. */
    expect($calls)->toBe(['prices', 'sectors', 'dividends'])
        ->and($report->summary())->toBe('1 cours, 1 secteur, 1 dividende')
        ->and($report->hasFailure())->toBeFalse()
        ->and(Price::where('asset_id', 1)->count())->toBe(1)
        ->and(SectorAllocation::where('asset_id', 1)->count())->toBe(1);
});
