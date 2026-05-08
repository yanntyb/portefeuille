<?php

use App\Domains\Asset\Infrastructure\Adapters\YahooFinanceAdapter;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;

it('gets current price for asset', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 125.50,
        'date' => '2026-05-07',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 130.00,
        'date' => '2026-05-08',
    ]);

    $adapter = new YahooFinanceAdapter();
    $price = $adapter->getCurrentPrice($security->id);

    expect($price)->toBe(130.00);
});

it('returns null when no prices exist', function () {
    $security = Security::factory()->create();

    $adapter = new YahooFinanceAdapter();
    $price = $adapter->getCurrentPrice($security->id);

    expect($price)->toBeNull();
});

it('gets price history for date range', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-05-01',
        'open' => 100.0,
        'high' => 102.0,
        'low' => 99.0,
        'close' => 101.0,
        'volume' => 1000,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-05-02',
        'open' => 101.0,
        'high' => 105.0,
        'low' => 100.0,
        'close' => 103.0,
        'volume' => 1500,
    ]);

    $adapter = new YahooFinanceAdapter();
    $history = $adapter->getPriceHistory($security->id);

    expect($history)->toHaveCount(2)
        ->and($history[0]['close'])->toBe(101.0)
        ->and($history[1]['close'])->toBe(103.0);
});

it('filters price history by date range', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-04-30',
        'close' => 100.0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-05-05',
        'close' => 110.0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-05-10',
        'close' => 120.0,
    ]);

    $adapter = new YahooFinanceAdapter();
    $history = $adapter->getPriceHistory($security->id, '2026-05-01', '2026-05-09');

    expect($history)->toHaveCount(1)
        ->and($history[0]['close'])->toBe(110.0);
});

it('supports Stock and ETF types', function () {
    $adapter = new YahooFinanceAdapter();

    expect($adapter->supports(AssetType::Stock))->toBeTrue()
        ->and($adapter->supports(AssetType::ETF))->toBeTrue()
        ->and($adapter->supports(AssetType::Crypto))->toBeFalse()
        ->and($adapter->supports(AssetType::RealEstate))->toBeFalse();
});
