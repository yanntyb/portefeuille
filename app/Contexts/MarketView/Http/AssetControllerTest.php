<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;

it('serves one address for every asset, whatever its exposure', function (InstrumentType $type) {
    $instrument = Instrument::factory()->create(['type' => $type]);

    $this->get(route('assets.show', $instrument->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Asset/Show'));
})->with([InstrumentType::Stock, InstrumentType::ETF, InstrumentType::Commodity, InstrumentType::Crypto]);

it('carries dividends on equity and withholds them elsewhere', function () {
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);

    $this->get(route('assets.show', $stock->id))
        ->assertInertia(fn ($page) => $page->has('dividends'));

    $this->get(route('assets.show', $gold->id))
        ->assertInertia(fn ($page) => $page->missing('dividends'));
});

it('answers 404 on an unknown asset', function () {
    $this->get(route('assets.show', 999999))->assertNotFound();
});
