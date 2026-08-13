<?php

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonProcessException;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->python = new FakePythonRunner;
    $this->adapter = new YahooFinanceAdapter($this->python);
});

function throwingAdapter(): YahooFinanceAdapter
{
    return new YahooFinanceAdapter(new class implements PythonRunner
    {
        public function run(string $script, array $input = [], ?int $timeout = null): PythonResult
        {
            throw new PythonProcessException('boom');
        }
    });
}

/**
 * A runner that times out the way Illuminate's process layer does: FakePythonRunner cannot
 * throw, and a timeout never surfaces as a PythonProcessException.
 */
function timingOutAdapter(): YahooFinanceAdapter
{
    return new YahooFinanceAdapter(new class implements PythonRunner
    {
        public function run(string $script, array $input = [], ?int $timeout = null): PythonResult
        {
            $process = new Process(['true']);

            throw new ProcessTimedOutException(
                new SymfonyTimeoutException($process, SymfonyTimeoutException::TYPE_GENERAL),
                new ProcessResult($process),
            );
        }
    });
}

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
    $this->python->withResult(YahooScript::Prices->path(), new PythonResult('ok', [
        ['date' => '2026-01-01', 'close' => 10.0],
        ['date' => '2026-01-02', 'close' => 20.5],
    ]));

    expect($this->adapter->getCurrentPrice($instrument->id))->toBe(20.5);
});

it('returns null for the current price on an error envelope', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult(YahooScript::Prices->path(), new PythonResult('error', error: 'boom'));

    expect($this->adapter->getCurrentPrice($instrument->id))->toBeNull();
});

it('returns null for the current price when data is empty', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult(YahooScript::Prices->path(), new PythonResult('ok', []));

    expect($this->adapter->getCurrentPrice($instrument->id))->toBeNull();
});

it('returns a populated history on a successful fetch', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult(YahooScript::Prices->path(), new PythonResult('ok', [
        ['date' => '2026-01-01', 'close' => 10.0],
        ['date' => '2026-01-02', 'close' => 11.0],
    ]));

    expect($this->adapter->getPriceHistory($instrument->id))
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2);
});

it('returns an empty history on an error envelope', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->python->withResult(YahooScript::Prices->path(), new PythonResult('error'));

    expect($this->adapter->getPriceHistory($instrument->id))->toBeEmpty();
});

it('builds an InstrumentData from a search hit with its sectors', function () {
    $this->python
        ->withResult(YahooScript::Search->path(), new PythonResult('ok', [
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'exchange' => 'NASDAQ'],
        ]))
        ->withResult(YahooScript::Sectors->path(), new PythonResult('ok', ['technology' => 1.0]));

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
    $this->python->withResult(YahooScript::Search->path(), new PythonResult('ok', []));

    expect($this->adapter->findBySymbol('NOPE', InstrumentType::Stock))->toBeNull();
});

it('maps known sector keys and skips unknown ones', function () {
    $this->python->withResult(YahooScript::Sectors->path(), new PythonResult('ok', [
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

it('swallows runner exceptions in getCurrentPrice', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    expect(throwingAdapter()->getCurrentPrice($instrument->id))->toBeNull();
});

it('swallows runner exceptions in getPriceHistory', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    expect(throwingAdapter()->getPriceHistory($instrument->id))->toBeEmpty();
});

it('swallows runner exceptions in findBySymbol', function () {
    expect(throwingAdapter()->findBySymbol('AAPL', InstrumentType::Stock))->toBeNull();
});

it('swallows runner exceptions in getSectorAllocations', function () {
    expect(throwingAdapter()->getSectorAllocations('AAPL', InstrumentType::ETF))->toBe([]);
});

it('includes the requested end date in the price history window', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->adapter->getPriceHistory($instrument->id, '2026-01-01', '2026-01-31');

    expect($this->python->calls[0]['input'])->toBe([
        'ticker' => 'AAPL',
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-01',
    ]);
});

it('includes today in the default price history window', function () {
    $this->travelTo('2026-08-13 10:00:00');
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->adapter->getPriceHistory($instrument->id);

    expect($this->python->calls[0]['input']['start_date'])->toBe('2025-08-13')
        ->and($this->python->calls[0]['input']['end_date'])->toBe('2026-08-14');
});

it('includes today when fetching the current price', function () {
    $this->travelTo('2026-08-13 10:00:00');
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->adapter->getCurrentPrice($instrument->id);

    expect($this->python->calls[0]['input']['end_date'])->toBe('2026-08-14');
});

it('sends one bulk entry per request with an inclusive end date', function () {
    $this->adapter->fetchPrices([
        new PriceRequestData('PE500.PA', '2026-08-11', '2026-08-13'),
        new PriceRequestData('AAPL', '2025-08-13', '2026-08-13'),
    ]);

    expect($this->python->calls[0]['script'])->toBe(YahooScript::PricesBulk->path())
        ->and($this->python->calls[0]['input']['tickers'])->toBe([
            ['ticker' => 'PE500.PA', 'start_date' => '2026-08-11', 'end_date' => '2026-08-14'],
            ['ticker' => 'AAPL', 'start_date' => '2025-08-13', 'end_date' => '2026-08-14'],
        ]);
});

it('maps the bulk payload to PriceData keyed by ticker', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('ok', [
        'AAPL' => [
            ['date' => '2026-08-12', 'open' => 1.0, 'high' => 2.0, 'low' => 0.5, 'close' => 1.5, 'volume' => 10],
            ['date' => '2026-08-13', 'open' => 1.5, 'high' => 2.5, 'low' => 1.0, 'close' => 2.0, 'volume' => 20],
        ],
    ]));

    $prices = $this->adapter->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);

    expect($prices)->toHaveKey('AAPL')
        ->and($prices['AAPL'])->toHaveCount(2)
        ->and($prices['AAPL'][0])->toBeInstanceOf(PriceData::class)
        ->and($prices['AAPL'][0]->date)->toBe('2026-08-12')
        ->and($prices['AAPL'][0]->close)->toBe(1.5)
        ->and($prices['AAPL'][0]->volume)->toBe(10);
});

it('omits tickers absent from the bulk payload', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('ok', []));

    expect($this->adapter->fetchPrices([new PriceRequestData('DEAD.PA', '2026-08-11', '2026-08-13')]))
        ->toBe([]);
});

it('never runs the script without requests', function () {
    expect($this->adapter->fetchPrices([]))->toBe([])
        ->and($this->python->calls)->toBe([]);
});

it('throws when the bulk script returns an error envelope', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('error', error: 'boom'));

    $this->adapter->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);
})->throws(PriceFeedException::class);

it('throws when the runner itself fails', function () {
    throwingAdapter()->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);
})->throws(PriceFeedException::class);

it('gives the bulk fetch a timeout sized for the whole catalogue', function () {
    $this->adapter->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);

    expect($this->python->calls[0]['timeout'])->toBeGreaterThan((int) config('python.timeout'));
});

it('turns a Python timeout into a PriceFeedException chaining its cause', function () {
    $caught = null;

    try {
        timingOutAdapter()->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);
    } catch (PriceFeedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(PriceFeedException::class)
        ->and($caught->getPrevious())->toBeInstanceOf(ProcessTimedOutException::class);
});

it('throws a PriceFeedException on a malformed row inside a successful envelope', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('ok', [
        'AAPL' => ['not-a-row'],
    ]));

    $this->adapter->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);
})->throws(PriceFeedException::class);
