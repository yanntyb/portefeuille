<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\MarketView\Ports\MarketDataPort;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->market = app(MarketDataPort::class);
});

it('lists all market instruments as summaries', function () {
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'World ETF', 'ticker' => 'IWDA', 'isin' => 'IE00B4L5Y983']);

    $summaries = $this->market->listInstruments();

    expect($summaries)->toHaveCount(1);
    expect($summaries[0]->name)->toBe('World ETF');
    expect($summaries[0]->ticker)->toBe('IWDA');
    expect($summaries[0]->isin)->toBe('IE00B4L5Y983');
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

it('carries the exposure of the instrument found', function () {
    $asset = Instrument::factory()->create(['type' => InstrumentType::Commodity, 'asset_class' => AssetClass::Commodity]);

    $meta = $this->market->findInstrument($asset->id);

    expect($meta->assetClass)->toBe(AssetClass::Commodity);
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

it('returns the close series of several assets in a single map, ordered by date', function () {
    $first = Instrument::factory()->create();
    $second = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $first->id, 'date' => '2026-01-02', 'close' => 110]);
    Price::factory()->create(['asset_id' => $first->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $second->id, 'date' => '2026-01-01', 'close' => 50]);

    $series = $this->market->closeSeriesSince(
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

    $series = $this->market->closeSeriesSince([$asset->id], Carbon::parse('2026-01-01'));

    expect($series[$asset->id])->toBe([100.0]);
});

it('omits the assets without any close in the window', function () {
    $priced = Instrument::factory()->create();
    $unpriced = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $priced->id, 'date' => '2026-01-01', 'close' => 100]);

    $series = $this->market->closeSeriesSince(
        [$priced->id, $unpriced->id],
        Carbon::parse('2026-01-01'),
    );

    expect($series)->toHaveKey($priced->id)
        ->and($series)->not->toHaveKey($unpriced->id);
});

it('returns an empty map for no asset', function () {
    expect($this->market->closeSeriesSince([], Carbon::parse('2026-01-01')))->toBe([]);
});

it('maps sector allocations to labelled weights', function () {
    $asset = Instrument::factory()->create();
    SectorAllocation::factory()->create(['asset_id' => $asset->id, 'sector' => Sector::Technology, 'weight' => 0.6]);

    $sectors = $this->market->sectors($asset->id);

    expect($sectors)->toHaveCount(1);
    expect($sectors[0]->label)->toBe('Technologie');
    expect($sectors[0]->weight)->toBe(0.6);
});

it('keeps only the assets carrying one of the given exposures', function () {
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    $ids = $this->market->idsOfClasses([$gold->id, $stock->id], [AssetClass::Commodity]);

    expect($ids)->toBe([$gold->id]);
});

it('leaves out an asset carrying the exposure but outside the given ids', function () {
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $otherGold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);

    $ids = $this->market->idsOfClasses([$gold->id], [AssetClass::Commodity]);

    expect($ids)->toBe([$gold->id])
        ->and($ids)->not->toContain($otherGold->id);
});

it('returns an empty list for no asset id', function () {
    expect($this->market->idsOfClasses([], [AssetClass::Commodity]))->toBe([]);
});

it('returns an empty list for no exposure', function () {
    $asset = Instrument::factory()->create();

    expect($this->market->idsOfClasses([$asset->id], []))->toBe([]);
});

it('lists only the instruments of the given exposure, ordered by name', function () {
    Instrument::factory()->create(['name' => 'Zeta']);
    Instrument::factory()->create(['name' => 'Alpha']);
    Instrument::factory()->ofType(InstrumentType::Commodity)->create(['name' => 'Or']);

    $summaries = $this->market->instrumentsOfClass(AssetClass::Equity);

    expect(array_map(fn ($summary): string => $summary->name, $summaries))->toBe(['Alpha', 'Zeta']);
});

it('returns the latest close of several assets in a single map', function () {
    $first = Instrument::factory()->create();
    $second = Instrument::factory()->create();
    $unpriced = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $first->id, 'date' => '2026-06-01', 'close' => 90]);
    Price::factory()->create(['asset_id' => $first->id, 'date' => '2026-07-01', 'close' => 120]);
    Price::factory()->create(['asset_id' => $second->id, 'date' => '2026-07-01', 'close' => 50]);

    $prices = $this->market->latestPricesFor([$first->id, $second->id, $unpriced->id]);

    expect($prices[$first->id])->toBe(120.0)
        ->and($prices[$second->id])->toBe(50.0)
        ->and($prices)->not->toHaveKey($unpriced->id);
});

it('returns an empty price map for no asset', function () {
    expect($this->market->latestPricesFor([]))->toBe([]);
});
