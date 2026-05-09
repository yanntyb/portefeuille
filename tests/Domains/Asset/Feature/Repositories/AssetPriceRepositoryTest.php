<?php

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Security\Models\Security;
use Carbon\Carbon;

it('finds the latest price for an asset', function (): void {
    $security = Security::factory()->create();
    AssetPrice::factory()->create(['asset_id' => $security->id, 'date' => '2026-05-06', 'close' => 100.0]);
    AssetPrice::factory()->create(['asset_id' => $security->id, 'date' => '2026-05-08', 'close' => 125.5]);

    $latest = app(AssetPriceRepositoryInterface::class)->findLatestForAsset($security->id);

    expect($latest)->not->toBeNull()
        ->and($latest->date->format('Y-m-d'))->toBe('2026-05-08');
});

it('returns null when no prices exist for asset', function (): void {
    $security = Security::factory()->create();

    expect(app(AssetPriceRepositoryInterface::class)->findLatestForAsset($security->id))->toBeNull();
});

it('finds a price for an asset on a specific date', function (): void {
    $security = Security::factory()->create();
    AssetPrice::factory()->create(['asset_id' => $security->id, 'date' => '2026-05-07']);

    $price = app(AssetPriceRepositoryInterface::class)
        ->findForAssetOnDate($security->id, Carbon::parse('2026-05-07'));

    expect($price)->not->toBeNull()
        ->and($price->date->format('Y-m-d'))->toBe('2026-05-07');
});

it('returns null when no price exists on date', function (): void {
    $security = Security::factory()->create();

    expect(
        app(AssetPriceRepositoryInterface::class)
            ->findForAssetOnDate($security->id, Carbon::parse('2026-05-07'))
    )->toBeNull();
});

it('returns prices for asset since a given date', function (): void {
    $security = Security::factory()->create();
    AssetPrice::factory()->create(['asset_id' => $security->id, 'date' => '2026-04-30']);
    AssetPrice::factory()->create(['asset_id' => $security->id, 'date' => '2026-05-01']);
    AssetPrice::factory()->create(['asset_id' => $security->id, 'date' => '2026-05-08']);

    $prices = app(AssetPriceRepositoryInterface::class)
        ->forAssetSince($security->id, Carbon::parse('2026-05-01'));

    expect($prices)->toHaveCount(2);
});

it('saves an asset price', function (): void {
    $security = Security::factory()->create();
    $price = new AssetPrice([
        'asset_id' => $security->id,
        'date' => '2026-05-09',
        'open' => 100.0,
        'high' => 102.0,
        'low' => 99.0,
        'close' => 101.0,
        'volume' => 5000,
    ]);

    app(AssetPriceRepositoryInterface::class)->save($price);

    $this->assertDatabaseHas('asset_prices', ['asset_id' => $security->id, 'close' => 101.0]);
});

it('gets latest date for multiple assets', function (): void {
    $s1 = Security::factory()->create();
    $s2 = Security::factory()->create();
    AssetPrice::factory()->create(['asset_id' => $s1->id, 'date' => '2026-05-06']);
    AssetPrice::factory()->create(['asset_id' => $s1->id, 'date' => '2026-05-08']);
    AssetPrice::factory()->create(['asset_id' => $s2->id, 'date' => '2026-05-07']);

    $dates = app(AssetPriceRepositoryInterface::class)->getLatestDateForAssets([$s1->id, $s2->id]);

    expect($dates[$s1->id])->toBe('2026-05-08')
        ->and($dates[$s2->id])->toBe('2026-05-07');
});

it('gets earliest date for multiple assets', function (): void {
    $s1 = Security::factory()->create();
    AssetPrice::factory()->create(['asset_id' => $s1->id, 'date' => '2026-04-01']);
    AssetPrice::factory()->create(['asset_id' => $s1->id, 'date' => '2026-05-08']);

    $dates = app(AssetPriceRepositoryInterface::class)->getEarliestDateForAssets([$s1->id]);

    expect($dates[$s1->id])->toBe('2026-04-01');
});

it('gets prices for multiple assets since a date', function (): void {
    $s1 = Security::factory()->create();
    $s2 = Security::factory()->create();
    AssetPrice::factory()->create(['asset_id' => $s1->id, 'date' => '2026-04-30']);
    AssetPrice::factory()->create(['asset_id' => $s1->id, 'date' => '2026-05-01']);
    AssetPrice::factory()->create(['asset_id' => $s2->id, 'date' => '2026-05-02']);

    $prices = app(AssetPriceRepositoryInterface::class)
        ->getForAssets([$s1->id, $s2->id], Carbon::parse('2026-05-01'));

    expect($prices)->toHaveCount(2);
});
