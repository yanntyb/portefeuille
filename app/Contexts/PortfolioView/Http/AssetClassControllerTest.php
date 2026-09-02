<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\PortfolioView\Datas\AnalysisData;
use App\Contexts\PortfolioView\Datas\AssetLineData;
use App\Contexts\PortfolioView\Datas\AssetValuationData;
use App\Contexts\PortfolioView\Datas\ClassSliceData;
use App\Contexts\PortfolioView\Datas\DrawdownData;
use App\Contexts\PortfolioView\Datas\EvolutionData;
use App\Contexts\PortfolioView\Datas\HoldingRowData;
use App\Contexts\PortfolioView\Datas\PerformanceLineData;
use App\Contexts\PortfolioView\Datas\PortfolioSummaryData;
use App\Contexts\PortfolioView\Datas\PositionData;
use App\Contexts\PortfolioView\Datas\SectorSliceData;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;

/** Un port de valorisation factice, servant les quatre lectures aux deux échelles. */
function fakeValuationPort(): ValuationPort
{
    return new class implements ValuationPort
    {
        public function performancesFor(int $userId, HoldingScope $scope): array
        {
            return [new PerformanceLineData('1M', 'Un mois', '2026-07-29', 900.0, 0.0, 100.0, 11.1)];
        }

        public function evolutionFor(int $userId, HoldingScope $scope): EvolutionData
        {
            return new EvolutionData(['2026-08-01'], [new AssetLineData(1, 'ACME', [1000.0], [800.0])]);
        }

        public function assetPerformancesFor(int $userId, int $assetId): array
        {
            return [];
        }

        public function assetSeriesFor(int $userId, int $assetId): AssetValuationData
        {
            return AssetValuationData::empty();
        }

        public function drawdownFor(int $userId, HoldingScope $scope): DrawdownData
        {
            return DrawdownData::empty();
        }
    };
}

/** Un port d'aperçu factice, tenant une position à 1 000 € payée 800 €. */
function fakeOverviewPort(): PortfolioOverviewPort
{
    return new class implements PortfolioOverviewPort
    {
        public function overviewFor(int $userId, HoldingScope $scope): PortfolioSummaryData
        {
            return new PortfolioSummaryData(1000.0, 800.0, 200.0, 25.0, 0.0, 0.0, [
                new HoldingRowData(
                    1, 'ACME', 'ACM', InstrumentType::Stock, $scope->classes[0] ?? AssetClass::Equity,
                    1, 'Compte-titres', AccountType::Cto,
                    10.0, 80.0, 100.0, 1000.0, 200.0, 25.0,
                ),
            ]);
        }

        /** @return list<ClassSliceData> */
        public function classBreakdownFor(int $userId, HoldingScope $scope): array
        {
            return [new ClassSliceData('equity', 'Actions', 1000.0, 100.0)];
        }

        public function positionFor(int $userId, int $assetId): ?PositionData
        {
            return null;
        }

        public function analysisFor(int $userId, HoldingScope $scope): AnalysisData
        {
            return AnalysisData::empty();
        }
    };
}

it('serves a list page for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetClass/Index')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel())
            ->where('assetClass.hasSectors', $class->hasSectors())
            ->missing('assetClass.hasIncome'));
})->with(AssetClass::cases());

/**
 * Même démonstration pour l'aperçu et l'évolution : la page se sert entièrement de ports
 * factices, donc elle ne lit plus rien de Portfolio ni de Valuation.
 */
it('sert l\'aperçu et l\'évolution par leurs seuls ports', function () {
    app()->instance(PortfolioOverviewPort::class, fakeOverviewPort());
    app()->instance(ValuationPort::class, fakeValuationPort());

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Index',
        'X-Inertia-Partial-Data' => 'overview,evolutionSeries',
    ];

    $response = $this->get(route('classes.equity'), $headers);

    $response->assertOk();
    expect($response->json('props.overview.totalValue'))->toEqual(1000.0)
        ->and($response->json('props.overview.holdings.0.typeLabel'))->toBe(InstrumentType::Stock->getLabel())
        ->and($response->json('props.evolutionSeries.perAsset.0.name'))->toBe('ACME');
});

/**
 * Rapatriée d'`AssetClassAnalysisControllerTest` : performances et secteurs sont revenus sur la
 * page d'exposition, en sections repliées, et gardent chacun leur groupe différé.
 */
it('sépare les groupes différés de la page, chaque groupe se chargeant seul', function () {
    cryptoFixture();

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->loadDeferredProps('secteurs', fn (Assert $reload) => $reload
                ->has('sectorBreakdown')
                ->missing('performances')
            )
            ->loadDeferredProps('performances', fn (Assert $reload) => $reload
                ->has('performances')
                ->missing('sectorBreakdown')
            )
        );
});

it('offers sectors on equity alone', function () {
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];

    $equity = $this->get(route('classes.equity'), $headers);
    $equity->assertOk();
    expect($equity->json('deferredProps'))->toHaveKeys(['tendances', 'evolution', 'performances', 'secteurs']);

    $crypto = $this->get(route('classes.crypto'), $headers);
    $crypto->assertOk();
    expect($crypto->json('deferredProps'))
        ->toHaveKey('performances')
        ->not->toHaveKey('secteurs')
        ->not->toHaveKey('revenus');
});

/**
 * La section ne se lit qu'à travers son port : `GetSectorBreakdown` n'est pas joignable depuis la
 * page. Un port factice suffit donc à la servir en entier, et c'est ce que ce test démontre.
 */
it('sert la section secteur par son seul port', function () {
    app()->instance(SectorBreakdownPort::class, new class implements SectorBreakdownPort
    {
        public function breakdownFor(int $userId, HoldingScope $scope): array
        {
            return [new SectorSliceData('Technologie', 200.0, 100.0, '#000000')];
        }
    });

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Index',
        'X-Inertia-Partial-Data' => 'sectorBreakdown',
    ];

    $response = $this->get(route('classes.equity'), $headers);

    $response->assertOk();
    expect($response->json('props.sectorBreakdown.0.label'))->toBe('Technologie');
});

it('sert les performances par leur seul port', function () {
    app()->instance(PortfolioOverviewPort::class, fakeOverviewPort());
    app()->instance(ValuationPort::class, fakeValuationPort());

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Index',
        'X-Inertia-Partial-Data' => 'performances',
    ];

    $response = $this->get(route('classes.equity'), $headers);

    $response->assertOk();
    expect($response->json('props.performances.0.key'))->toBe('1M');
});

it('defers the sector breakdown and loads it on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'ACME ETF']);
    SectorAllocation::factory()->create([
        'asset_id' => $asset->id,
        'sector' => Sector::Technology,
        'weight' => 1.0,
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->missing('sectorBreakdown')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('sectorBreakdown', 1)
                ->where('sectorBreakdown.0.label', 'Technologie')
                ->where('sectorBreakdown.0.value', fn ($value) => (float) $value === 1000.0)
                ->where('sectorBreakdown.0.pct', fn ($value) => (float) $value === 100.0)
                ->has('sectorBreakdown.0.color')
            )
        );
});

it('defers the portfolio performances and loads them on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->missing('performances')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('performances')
                ->where('performances.0.key', 'YTD')
                ->where('performances.0.startDate', '2026-01-01')
                ->has('performances.0.gain')
                ->has('performances.0.contributions')
                ->has('performances.0.valueStart')
            )
        );
});

it('diffère l’analyse de la classe et la charge à la demande', function () {
    portfolioFixture();

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->missing('basketAnalysis')
            ->loadDeferredProps('analyse', fn (Assert $reload) => $reload
                ->has('basketAnalysis.instruments.0.label')
                ->has('basketAnalysis.correlations')
                ->has('basketAnalysis.maxDrawdown')
                ->has('basketAnalysis.high52wGapPct')
                ->missing('performances')
            )
        );
});
