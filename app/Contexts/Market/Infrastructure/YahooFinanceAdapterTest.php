<?php

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Models\Instrument;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonProcessException;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->python = new FakePythonRunner;
    $this->adapter = new YahooFinanceAdapter($this->python);
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

it('returns the latest close from a successful price fetch', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult('fetch_prices.py', new PythonResult('ok', [
        ['date' => '2026-01-01', 'close' => 10.0],
        ['date' => '2026-01-02', 'close' => 20.5],
    ]));

    expect($this->adapter->getCurrentPrice($instrument->id))->toBe(20.5);
});

it('returns null for the current price on an error envelope', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult('fetch_prices.py', new PythonResult('error', error: 'boom'));

    expect($this->adapter->getCurrentPrice($instrument->id))->toBeNull();
});

it('returns null for the current price when data is empty', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult('fetch_prices.py', new PythonResult('ok', []));

    expect($this->adapter->getCurrentPrice($instrument->id))->toBeNull();
});

it('returns a populated history on a successful fetch', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult('fetch_prices.py', new PythonResult('ok', [
        ['date' => '2026-01-01', 'close' => 10.0],
        ['date' => '2026-01-02', 'close' => 11.0],
    ]));

    expect($this->adapter->getPriceHistory($instrument->id))
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2);
});

it('returns an empty history on an error envelope', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult('fetch_prices.py', new PythonResult('error'));

    expect($this->adapter->getPriceHistory($instrument->id))->toBeEmpty();
});

it('builds an InstrumentData from a search hit with its sectors', function () {
    $this->python
        ->withResult('search_ticker.py', new PythonResult('ok', [
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'exchange' => 'NASDAQ'],
        ]))
        ->withResult('fetch_sectors.py', new PythonResult('ok', ['technology' => 1.0]));

    $data = $this->adapter->findBySymbol('AAPL', InstrumentType::Stock);

    expect($data)->toBeInstanceOf(InstrumentData::class)
        ->and($data->symbol)->toBe('AAPL')
        ->and($data->name)->toBe('Apple Inc.')
        ->and($data->type)->toBe(InstrumentType::Stock)
        ->and($data->exchange)->toBe('NASDAQ')
        ->and($data->sectors)->toHaveCount(1)
        ->and($data->sectors[0]->sector)->toBe(Sector::Technology);
});

it('returns null from findBySymbol when the search is empty', function () {
    $this->python->withResult('search_ticker.py', new PythonResult('ok', []));

    expect($this->adapter->findBySymbol('NOPE', InstrumentType::Stock))->toBeNull();
});

it('maps known sector keys and skips unknown ones', function () {
    $this->python->withResult('fetch_sectors.py', new PythonResult('ok', [
        'technology' => '0.6',
        'financial_services' => 0.4,
        'unknown_sector' => 0.1,
    ]));

    $allocations = $this->adapter->getSectorAllocations('AAPL', InstrumentType::ETF);

    expect($allocations)->toHaveCount(2)
        ->and($allocations[0]->sector)->toBe(Sector::Technology)
        ->and($allocations[0]->weight)->toBe(0.6)
        ->and($allocations[1]->sector)->toBe(Sector::FinancialServices)
        ->and($allocations[1]->weight)->toBe(0.4);
});

it('swallows runner exceptions and returns the empty value', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $adapter = new YahooFinanceAdapter(new class implements PythonRunner
    {
        public function run(string $script, array $input = [], ?int $timeout = null): PythonResult
        {
            throw new PythonProcessException('boom');
        }
    });

    expect($adapter->getCurrentPrice($instrument->id))->toBeNull();
});
