<?php

use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedException;
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

it('feeds prices for every quoted type but bonds', function () {
    expect($this->adapter->supportsPriceFeed(InstrumentType::Stock))->toBeTrue()
        ->and($this->adapter->supportsPriceFeed(InstrumentType::ETF))->toBeTrue()
        ->and($this->adapter->supportsPriceFeed(InstrumentType::Crypto))->toBeTrue()
        ->and($this->adapter->supportsPriceFeed(InstrumentType::Commodity))->toBeTrue()
        ->and($this->adapter->supportsPriceFeed(InstrumentType::Bond))->toBeFalse();
});

it('covers the same types on the instrument and price providers', function (InstrumentType $type) {
    expect($this->adapter->supportsInstruments($type))->toBe($this->adapter->supportsPriceFeed($type))
        ->and($this->adapter->supportsPrices($type))->toBe($this->adapter->supportsPriceFeed($type));
})->with(InstrumentType::cases());

it('only breaks down sectors for Stock and ETF', function () {
    expect($this->adapter->supportsSectors(InstrumentType::Stock))->toBeTrue()
        ->and($this->adapter->supportsSectors(InstrumentType::ETF))->toBeTrue()
        ->and($this->adapter->supportsSectors(InstrumentType::Crypto))->toBeFalse()
        ->and($this->adapter->supportsSectors(InstrumentType::Commodity))->toBeFalse()
        ->and($this->adapter->supportsSectors(InstrumentType::Bond))->toBeFalse();
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

it('ne couvre les dividendes que pour les actions et les ETF', function () {
    $adapter = app(YahooFinanceAdapter::class);

    expect($adapter->supportsDividendFeed(InstrumentType::Stock))->toBeTrue()
        ->and($adapter->supportsDividendFeed(InstrumentType::ETF))->toBeTrue()
        ->and($adapter->supportsDividendFeed(InstrumentType::Crypto))->toBeFalse()
        ->and($adapter->supportsDividendFeed(InstrumentType::Commodity))->toBeFalse()
        ->and($adapter->supportsDividendFeed(InstrumentType::Bond))->toBeFalse();
});

it('décode les détachements du script et décale la borne haute d\'un jour', function () {
    $runner = (new FakePythonRunner)->withResult(
        YahooScript::DividendsBulk->path(),
        new PythonResult(status: 'ok', data: ['CW8.PA' => [
            ['ex_date' => '2026-03-05', 'amount_per_share' => 0.51],
        ]]),
    );

    $dividends = (new YahooFinanceAdapter($runner))->fetchDividends([
        new DividendRequestData('CW8.PA', '2026-01-01', '2026-06-30'),
    ]);

    expect($dividends['CW8.PA'][0])->toBeInstanceOf(DividendData::class)
        ->and($dividends['CW8.PA'][0]->exDate)->toBe('2026-03-05')
        ->and($dividends['CW8.PA'][0]->amountPerShare)->toBe(0.51)
        ->and($runner->calls[0]['input']['tickers'][0]['end_date'])->toBe('2026-07-01');
});

it('donne au lot de dividendes un timeout dimensionné pour tout le catalogue', function () {
    $runner = new FakePythonRunner;

    (new YahooFinanceAdapter($runner))->fetchDividends([
        new DividendRequestData('CW8.PA', '2026-01-01', '2026-06-30'),
    ]);

    expect($runner->calls[0]['timeout'])->toBeGreaterThan((int) config('python.timeout'));
});

it('n\'appelle pas le script sans demande', function () {
    $runner = new FakePythonRunner;

    expect((new YahooFinanceAdapter($runner))->fetchDividends([]))->toBe([])
        ->and($runner->calls)->toBe([]);
});

it('lève une exception de feed quand le script échoue', function () {
    $runner = (new FakePythonRunner)->withResult(
        YahooScript::DividendsBulk->path(),
        new PythonResult(status: 'error', error: 'yfinance rate limited'),
    );

    expect(fn () => (new YahooFinanceAdapter($runner))->fetchDividends([
        new DividendRequestData('CW8.PA', '2026-01-01', '2026-06-30'),
    ]))->toThrow(DividendFeedException::class, 'yfinance rate limited');
});

it('traduit les types Yahoo en types d\'instrument', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'ok', data: [
        ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'exchange' => 'NasdaqGS', 'type' => 'Equity'],
        ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
        ['symbol' => 'BTC-EUR', 'name' => 'Bitcoin EUR', 'exchange' => 'CCC', 'type' => 'Cryptocurrency'],
        ['symbol' => 'SI=F', 'name' => 'Silver', 'exchange' => 'NY Mercantile', 'type' => 'Future'],
    ]));

    $results = $this->adapter->searchInstruments('a');

    expect($results)->toHaveCount(4)
        ->and($results[0]->symbol)->toBe('AAPL')
        ->and($results[0]->name)->toBe('Apple Inc.')
        ->and($results[0]->exchange)->toBe('NasdaqGS')
        ->and($results[0]->type)->toBe(InstrumentType::Stock)
        ->and($results[1]->type)->toBe(InstrumentType::ETF)
        ->and($results[2]->type)->toBe(InstrumentType::Crypto)
        ->and($results[3]->type)->toBe(InstrumentType::Commodity);
});

it('garde un résultat dont le type Yahoo est inconnu, sans type', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'ok', data: [
        ['symbol' => '^FCHI', 'name' => 'CAC 40', 'exchange' => 'Paris', 'type' => 'Index'],
    ]));

    $results = $this->adapter->searchInstruments('cac');

    /** Le résultat s'affiche quand même : c'est l'écran de confirmation qui tranchera. */
    expect($results)->toHaveCount(1)
        ->and($results[0]->type)->toBeNull();
});

it('écarte un résultat sans symbole', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'ok', data: [
        ['symbol' => '', 'name' => 'Sans symbole', 'exchange' => null, 'type' => 'Equity'],
        ['symbol' => 'MSFT', 'name' => 'Microsoft', 'exchange' => 'NasdaqGS', 'type' => 'Equity'],
    ]));

    expect($this->adapter->searchInstruments('m'))->toHaveCount(1);
});

it('rend une liste vide quand le script échoue', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'error', error: 'boom'));

    expect($this->adapter->searchInstruments('aapl'))->toBe([]);
});

it('rend une liste vide quand le process Python casse', function () {
    expect(throwingAdapter()->searchInstruments('aapl'))->toBe([]);
});

it('passe la requête au script de recherche', function () {
    $this->adapter->searchInstruments('lvmh');

    expect($this->python->calls[0]['script'])->toBe(YahooScript::Search->path())
        ->and($this->python->calls[0]['input'])->toBe(['query' => 'lvmh']);
});
