<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Asset;
use App\Domains\Asset\Models\ETF;

it('ETF has correct asset type', function () {
    $etf = ETF::factory()->create();
    expect($etf->type)->toBe(AssetType::ETF);
});

it('ETF factory creates record with type etf', function () {
    $etf = ETF::factory()->create();
    expect($etf->type->value)->toBe('etf');
});

it('ETF is instance of Asset', function () {
    $etf = ETF::factory()->create();
    expect($etf)->toBeInstanceOf(Asset::class);
});
