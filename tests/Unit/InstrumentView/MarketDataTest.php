<?php

use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->market = app(MarketDataPort::class);
});

it('lists all market instruments as summaries', function () {
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'World ETF', 'ticker' => 'IWDA']);

    $summaries = $this->market->listInstruments();

    expect($summaries)->toHaveCount(1);
    expect($summaries[0]->name)->toBe('World ETF');
    expect($summaries[0]->type)->toBe(InstrumentType::ETF);
});

it('finds an instrument with its latest price', function () {
    $asset = Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'ACM', 'isin' => 'US0000000001']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-06-01', 'close' => 90]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $meta = $this->market->findInstrument($asset->id);

    expect($meta->name)->toBe('ACME');
    expect($meta->isin)->toBe('US0000000001');
    expect($meta->lastPrice)->toBe(120.0);
    expect($meta->lastPriceDate)->toBe('2026-07-01');
});

it('returns null meta for an unknown instrument', function () {
    expect($this->market->findInstrument(999))->toBeNull();
});

it('returns the latest price for an instrument', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 55.5]);

    expect($this->market->latestPrice($asset->id))->toBe(55.5);
    expect($this->market->latestPrice(999))->toBeNull();
});

it('builds price history since a date as parallel arrays', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 20]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2025-01-01', 'close' => 5]);

    $history = $this->market->priceHistory($asset->id, Carbon::parse('2026-01-01'));

    expect($history->labels)->toBe(['2026-01-01', '2026-02-01']);
    expect($history->close)->toBe([10.0, 20.0]);
});

it('maps sector allocations to labelled weights', function () {
    $asset = Instrument::factory()->create();
    SectorAllocation::factory()->create(['asset_id' => $asset->id, 'sector' => Sector::Technology, 'weight' => 0.6]);

    $sectors = $this->market->sectors($asset->id);

    expect($sectors)->toHaveCount(1);
    expect($sectors[0]->label)->toBe('Technologie');
    expect($sectors[0]->weight)->toBe(0.6);
});
