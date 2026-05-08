<?php

use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;

it('belongs to a security', function () {
    $security = Security::factory()->create();
    $price = 
SecurityPrice::factory()->create(['security_id' => $security->id]);

    expect($price->security->id)->toBe($security->id);
});

it('casts date to Carbon instance', function () {
    $price = 
SecurityPrice::factory()->create(['date' => '2024-06-15']);

    expect($price->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($price->date->format('Y-m-d'))->toBe('2024-06-15');
});

it('casts OHLC fields as decimals', function () {
    $price = 
SecurityPrice::factory()->create([
        'open' => 100.1234,
        'high' => 105.5678,
        'low' => 98.9012,
        'close' => 103.4567,
    ]);

    $price->refresh();

    expect($price->open)->toBe('100.1234')
        ->and($price->high)->toBe('105.5678')
        ->and($price->low)->toBe('98.9012')
        ->and($price->close)->toBe('103.4567');
});

it('casts volume as integer', function () {
    $price = 
SecurityPrice::factory()->create(['volume' => 50000]);

    $price->refresh();

    expect($price->volume)->toBe(50000);
});
