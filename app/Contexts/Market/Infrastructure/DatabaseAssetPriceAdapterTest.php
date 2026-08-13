<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->adapter = new DatabaseAssetPriceAdapter(new EloquentPriceRepository);
    $this->instrument = Instrument::factory()->create();
});

it('returns the current price (latest close) as a float', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-01', 'close' => 10]);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01', 'close' => 42.5]);

    expect($this->adapter->getCurrentPrice($this->instrument->id))->toBe(42.5);
});

it('returns null for the current price when there is no data', function () {
    expect($this->adapter->getCurrentPrice($this->instrument->id))->toBeNull();
});

it('returns a history mapped to arrays', function () {
    Price::factory()->create([
        'asset_id' => $this->instrument->id,
        'date' => '2026-02-01',
        'open' => 1, 'high' => 2, 'low' => 0.5, 'close' => 1.5, 'volume' => 100,
    ]);

    $history = $this->adapter->getPriceHistory($this->instrument->id, '2026-01-01');

    expect($history)->toBeInstanceOf(Collection::class)
        ->and($history)->toHaveCount(1)
        ->and($history->first())->toMatchArray([
            'date' => '2026-02-01',
            'close' => 1.5,
            'volume' => 100,
        ]);
});

it('filters the history by end date', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-05-01']);

    expect($this->adapter->getPriceHistory($this->instrument->id, '2026-01-01', '2026-03-01'))
        ->toHaveCount(1);
});

it('supports any instrument type', function () {
    expect($this->adapter->supportsPrices(InstrumentType::Crypto))->toBeTrue();
});
