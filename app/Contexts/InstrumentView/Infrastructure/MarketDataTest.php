<?php

use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Carbon;

it('returns the close series of several assets in a single map, ordered by date', function () {
    $first = Instrument::factory()->create();
    $second = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $first->id, 'date' => '2026-01-02', 'close' => 110]);
    Price::factory()->create(['asset_id' => $first->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $second->id, 'date' => '2026-01-01', 'close' => 50]);

    $series = app(MarketDataPort::class)->closeSeriesSince(
        [$first->id, $second->id],
        Carbon::parse('2026-01-01'),
    );

    expect($series[$first->id])->toBe([100.0, 110.0])
        ->and($series[$second->id])->toBe([50.0]);
});

it('omits the closes older than the given date', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2025-12-31', 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $series = app(MarketDataPort::class)->closeSeriesSince([$asset->id], Carbon::parse('2026-01-01'));

    expect($series[$asset->id])->toBe([100.0]);
});

it('omits the assets without any close in the window', function () {
    $priced = Instrument::factory()->create();
    $unpriced = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $priced->id, 'date' => '2026-01-01', 'close' => 100]);

    $series = app(MarketDataPort::class)->closeSeriesSince(
        [$priced->id, $unpriced->id],
        Carbon::parse('2026-01-01'),
    );

    expect($series)->toHaveKey($priced->id)
        ->and($series)->not->toHaveKey($unpriced->id);
});

it('returns an empty map for no asset', function () {
    expect(app(MarketDataPort::class)->closeSeriesSince([], Carbon::parse('2026-01-01')))->toBe([]);
});
