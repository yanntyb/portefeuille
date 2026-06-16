<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->adapter = new YahooFinanceAdapter;
});

it('supports Stock and ETF but not Crypto or Bond', function () {
    expect($this->adapter->supports(InstrumentType::Stock))->toBeTrue()
        ->and($this->adapter->supports(InstrumentType::ETF))->toBeTrue()
        ->and($this->adapter->supports(InstrumentType::Crypto))->toBeFalse()
        ->and($this->adapter->supports(InstrumentType::Bond))->toBeFalse();
});

it('returns null for the current price without a ticker', function () {
    $instrument = Instrument::factory()->create(['ticker' => null]);

    expect($this->adapter->getCurrentPrice($instrument->id))->toBeNull();
});

it('returns null for the current price of a non-existent asset', function () {
    expect($this->adapter->getCurrentPrice(999))->toBeNull();
});

it('returns an empty history without a ticker', function () {
    $instrument = Instrument::factory()->create(['ticker' => null]);

    expect($this->adapter->getPriceHistory($instrument->id))
        ->toBeInstanceOf(Collection::class)
        ->toBeEmpty();
});
