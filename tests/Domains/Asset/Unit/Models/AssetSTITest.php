<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Asset;
use App\Domains\Asset\Models\Bond;
use App\Domains\Asset\Models\Crypto;
use App\Domains\Asset\Models\ETF;
use App\Domains\Asset\Models\RealEstate;
use App\Domains\Asset\Models\Savings;
use App\Domains\Asset\Models\Stock;
use App\Domains\Security\Models\Security;
use Illuminate\Support\Facades\DB;

it('Asset is not abstract', function (): void {
    $reflection = new ReflectionClass(Asset::class);
    expect($reflection->isAbstract())->toBeFalse();
});

it('dispatches to Stock for type stock', function (): void {
    $security = Security::factory()->create();
    DB::table('securities')->where('id', $security->id)->update(['type' => AssetType::Stock->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Stock::class);
});

it('dispatches to ETF for type etf', function (): void {
    $security = Security::factory()->create();
    DB::table('securities')->where('id', $security->id)->update(['type' => AssetType::ETF->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(ETF::class);
});

it('dispatches to Crypto for type crypto', function (): void {
    $security = Security::factory()->create();
    DB::table('securities')->where('id', $security->id)->update(['type' => AssetType::Crypto->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Crypto::class);
});

it('dispatches to RealEstate for type real_estate', function (): void {
    $security = Security::factory()->create();
    DB::table('securities')->where('id', $security->id)->update(['type' => AssetType::RealEstate->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(RealEstate::class);
});

it('dispatches to Bond for type bond', function (): void {
    $security = Security::factory()->create();
    DB::table('securities')->where('id', $security->id)->update(['type' => AssetType::Bond->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Bond::class);
});

it('dispatches to Savings for type savings', function (): void {
    $security = Security::factory()->create();
    DB::table('securities')->where('id', $security->id)->update(['type' => AssetType::Savings->value]);
    $security->refresh();
    $asset = Asset::find($security->id);
    expect($asset)->toBeInstanceOf(Savings::class);
});

it('Asset::all returns correct subclasses', function (): void {
    $stock = Security::factory()->create();
    DB::table('securities')->where('id', $stock->id)->update(['type' => AssetType::Stock->value]);

    $etf = Security::factory()->create();
    DB::table('securities')->where('id', $etf->id)->update(['type' => AssetType::ETF->value]);

    $classes = Asset::all()->map(fn ($a) => get_class($a))->values()->toArray();

    expect($classes)->toContain(Stock::class)
        ->and($classes)->toContain(ETF::class);
});
