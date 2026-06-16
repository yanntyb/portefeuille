<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Carbon;

it('casts the columns', function () {
    $price = Price::factory()->create([
        'date' => '2026-01-15',
        'close' => 123.4567,
        'volume' => 999,
    ]);

    $price->refresh();

    expect($price->date)->toBeInstanceOf(Carbon::class)
        ->and($price->volume)->toBeInt()
        ->and($price->close)->toBe('123.4567');
});

it('belongs to an instrument', function () {
    $instrument = Instrument::factory()->create();
    $price = Price::factory()->create(['asset_id' => $instrument->id]);

    expect($price->instrument)->toBeInstanceOf(Instrument::class)
        ->and($price->instrument->id)->toBe($instrument->id);
});
