<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Http\Middleware\HandleInertiaRequests;

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

/**
 * La mini-timeline du graphe ne peut révéler que ce qui lui est servi : douze mois la figeaient
 * sur son plancher d'un an, sans rien à dérouler.
 */
it('sert cinq ans de cours, de quoi alimenter la mini-timeline du graphe', function () {
    $instrument = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    $old = now()->subMonths(48)->startOfDay();
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => $old]);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now()->subMonth()->startOfDay()]);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now()->subMonths(72)->startOfDay()]);

    $partial = $this->get(route('assets.show', $instrument->id), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Asset/Show',
        'X-Inertia-Partial-Data' => 'priceHistory',
    ]);

    expect($partial->json('props.priceHistory.labels'))
        ->toContain($old->format('Y-m-d'))
        ->not->toContain(now()->subMonths(72)->format('Y-m-d'));
});

it('answers 404 on an unknown asset', function () {
    $this->get(route('assets.show', 999999))->assertNotFound();
});
