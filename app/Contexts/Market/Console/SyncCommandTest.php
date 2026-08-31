<?php

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedPort;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\SectorProviderPort;

beforeEach(function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
});

it('prints the summary of the three sources', function () {
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn([
            'AAPL' => [new PriceData(date: '2026-08-31', close: 10.0)],
        ]);
    });

    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturn([
            new SectorAllocationData(sector: Sector::Technology, weight: 1.0),
        ]);
    });

    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andReturn([]);
    });

    $this->artisan('market:sync')
        /** Un instrument par source, pas une ligne écrite : le ticker sans détachement compte. */
        ->expectsOutputToContain('1 cours, 1 secteur, 1 dividende')
        ->assertSuccessful();
});

it('fails when a source came back empty-handed', function () {
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(false);
    });

    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andReturn([]);
    });

    $this->artisan('market:sync')
        ->expectsOutputToContain('AAPL : échec des cours')
        ->assertFailed();
});
