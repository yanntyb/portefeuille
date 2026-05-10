<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Assets\Bond;
use App\Domains\Asset\Models\Assets\Crypto;
use App\Domains\Asset\Models\Assets\ETF;
use App\Domains\Asset\Models\Assets\RealEstate;
use App\Domains\Asset\Models\Assets\Savings;
use App\Domains\Asset\Models\Assets\Stock;

it('Asset is not abstract', function () {
    $reflection = new ReflectionClass(Asset::class);
    expect($reflection->isAbstract())->toBeFalse();
});

it('dispatches to Stock for type stock', function () {
    $security = Stock::factory()->create();
    Asset::query()->where('id', $security->id)->update(['type' => AssetType::Stock->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Stock::class);
});

it('dispatches to ETF for type etf', function () {
    $security = Stock::factory()->create();
    Asset::query()->where('id', $security->id)->update(['type' => AssetType::ETF->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(ETF::class);
});

it('dispatches to Crypto for type crypto', function () {
    $security = Stock::factory()->create();
    Asset::query()->where('id', $security->id)->update(['type' => AssetType::Crypto->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Crypto::class);
});

it('dispatches to RealEstate for type real_estate', function () {
    $security = Stock::factory()->create();
    Asset::query()->where('id', $security->id)->update(['type' => AssetType::RealEstate->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(RealEstate::class);
});

it('dispatches to Bond for type bond', function () {
    $security = Stock::factory()->create();
    Asset::query()->where('id', $security->id)->update(['type' => AssetType::Bond->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Bond::class);
});

it('dispatches to Savings for type savings', function () {
    $security = Stock::factory()->create();
    Asset::query()->where('id', $security->id)->update(['type' => AssetType::Savings->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Savings::class);
});

it('Asset::all returns correct subclasses', function () {
    $stock = Stock::factory()->create();
    Asset::query()->where('id', $stock->id)->update(['type' => AssetType::Stock->value]);

    $etf = Stock::factory()->create();
    Asset::query()->where('id', $etf->id)->update(['type' => AssetType::ETF->value]);

    $classes = Asset::all()->map(fn ($a) => get_class($a))->values()->toArray();

    expect($classes)->toContain(Stock::class)
        ->and($classes)->toContain(ETF::class);
});
