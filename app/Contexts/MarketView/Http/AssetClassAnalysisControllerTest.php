<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\MarketView\Datas\AnalysisData;
use App\Contexts\MarketView\Datas\AssetLineData;
use App\Contexts\MarketView\Datas\AssetValuationData;
use App\Contexts\MarketView\Datas\DividendHistoryData;
use App\Contexts\MarketView\Datas\DrawdownData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\HoldingRowData;
use App\Contexts\MarketView\Datas\IncomeOverviewData;
use App\Contexts\MarketView\Datas\IncomeYearData;
use App\Contexts\MarketView\Datas\PerformanceLineData;
use App\Contexts\MarketView\Datas\PortfolioSummaryData;
use App\Contexts\MarketView\Datas\PositionData;
use App\Contexts\MarketView\Datas\SectorSliceData;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Déménagée depuis `AssetClassControllerTest` (« serves a list page for every exposure ») :
 * `hasSectors`/`hasIncome` ne voyagent plus avec la page liste, mais avec la page analyse.
 */
it('serves the hasSectors/hasIncome flags for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}.analyse"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
            ->where('assetClass.hasSectors', $class->hasSectors())
            ->where('assetClass.hasIncome', $class === AssetClass::Equity));
})->with(AssetClass::cases());

it('rend la page analyse d\'une exposition', function () {
    cryptoFixture();

    $this->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
            ->where('assetClass.key', 'equity')
            ->where('assetClass.label', 'Actions')
            ->where('assetClass.slug', 'actions')
        );
});

it('sert une page analyse par exposition', function () {
    cryptoFixture();

    foreach (['/actions/analyse', '/crypto/analyse', '/obligations/analyse', '/matieres-premieres/analyse'] as $url) {
        $this->get($url)->assertOk();
    }
});

/**
 * Déménagée depuis `InstrumentsPageTest` (« sépare les propriétés différées par section, chaque
 * groupe se chargeant seul ») : la page analyse porte désormais quatre groupes différés
 * (`analyses`, `performances`, `secteurs`, `revenus`), et rien ne vérifiait qu'ils arrivent
 * séparément.
 */
it('sépare les groupes différés de la page analyse, chaque groupe se chargeant seul', function () {
    cryptoFixture();

    $this->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
            ->loadDeferredProps('secteurs', fn (Assert $reload) => $reload
                ->has('sectorBreakdown')
                ->missing('performances')
                ->missing('income')
                ->missing('analysis')
            )
            ->loadDeferredProps('performances', fn (Assert $reload) => $reload
                ->has('performances')
                ->missing('sectorBreakdown')
                ->missing('income')
            )
            ->loadDeferredProps('revenus', fn (Assert $reload) => $reload
                ->has('income')
                ->has('annualIncome')
                ->missing('sectorBreakdown')
                ->missing('performances')
            )
            ->loadDeferredProps('analyses', fn (Assert $reload) => $reload
                ->has('analysis')
                ->has('drawdown')
                ->missing('sectorBreakdown')
                ->missing('performances')
            )
        );
});

/**
 * `assertInertia()` s'appuie sur `assertViewHas('page')`, qui suppose un rendu Blade complet :
 * une requête partielle (en-tête `X-Inertia`) ne produit que du JSON, sans vue. C'est pourquoi
 * `AssetClassControllerTest` et `AssetControllerTest` lisent une prop différée via `->json(...)`
 * plutôt que via `assertInertia()` — on reprend la même façon de faire ici.
 */
it('résout les analyses en prop différée', function () {
    cryptoFixture();

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Analysis',
        'X-Inertia-Partial-Data' => 'analysis',
    ];

    $response = $this->get('/actions/analyse', $headers);

    $response->assertOk();
    expect($response->json('props.analysis.concentration.top1'))->toEqual(100.0);
});

/**
 * Déménagée depuis `AssetClassControllerTest` (« offers sectors and income on equity alone »).
 *
 * `sectorBreakdown` et `income` voyagent en props différées : absentes de `props` à la première
 * réponse, elles n'apparaissent que dans `deferredProps`, comme le vérifie déjà
 * `CryptoControllerTest` pour `tendances`/`performances`/`evolution`.
 */
it('offers sectors and income on equity alone', function () {
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];

    $equity = $this->get(route('classes.equity.analyse'), $headers);
    $equity->assertOk();
    expect($equity->json('deferredProps'))->toHaveKeys(['secteurs', 'revenus']);

    $crypto = $this->get(route('classes.crypto.analyse'), $headers);
    $crypto->assertOk();
    expect($crypto->json('deferredProps'))
        ->not->toHaveKey('secteurs')
        ->not->toHaveKey('revenus');
});

/**
 * Déménagée depuis `AssetClassControllerTest` (« sert les sections secteur et revenus par leurs
 * seuls ports »).
 *
 * Les deux sections ne se lisent qu'à travers leurs ports : ni `GetSectorBreakdown`, ni
 * `GetIncomeSummary`, ni `IncomeSource` ne sont joignables depuis la page. Des ports factices
 * suffisent donc à la servir en entier, et c'est ce que ce test démontre.
 */
it('sert les sections secteur et revenus par leurs seuls ports', function () {
    app()->instance(SectorBreakdownPort::class, new class implements SectorBreakdownPort
    {
        public function breakdownFor(int $userId): array
        {
            return [new SectorSliceData('Technologie', 200.0, 100.0, '#000000')];
        }
    });

    app()->instance(IncomePort::class, new class implements IncomePort
    {
        public function supportsExposure(AssetClass $exposure): bool
        {
            return $exposure === AssetClass::Equity;
        }

        public function summaryFor(int $userId, AssetClass $exposure): IncomeOverviewData
        {
            return new IncomeOverviewData(13.0, 8.0, 9.0, ['dividend' => 13.0]);
        }

        public function annualFor(int $userId, AssetClass $exposure): array
        {
            return [new IncomeYearData(2026, 8.0, ['dividend' => 8.0])];
        }

        public function assetHistoryFor(int $userId, int $assetId): DividendHistoryData
        {
            return DividendHistoryData::empty();
        }
    });

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Analysis',
        'X-Inertia-Partial-Data' => 'sectorBreakdown,income,annualIncome',
    ];

    $response = $this->get(route('classes.equity.analyse'), $headers);

    $response->assertOk();
    expect($response->json('props.sectorBreakdown.0.label'))->toBe('Technologie')
        ->and($response->json('props.income.totalReceived'))->toEqual(13.0)
        ->and($response->json('props.annualIncome.0.year'))->toBe(2026);
});

/**
 * Déménagée depuis `AssetClassControllerTest` (« sert l'aperçu, les performances et l'évolution
 * par leurs seuls ports » — volet performances).
 *
 * La page analyse ne lit les performances qu'à travers `ValuationPort` : un port factice suffit
 * à la servir en entier.
 */
it('sert les performances par leur seul port', function () {
    app()->instance(PortfolioOverviewPort::class, new class implements PortfolioOverviewPort
    {
        public function overviewFor(int $userId, AssetClass $exposure): PortfolioSummaryData
        {
            return new PortfolioSummaryData(1000.0, 800.0, 200.0, 25.0, [
                new HoldingRowData(
                    1, 'ACME', 'ACM', InstrumentType::Stock, $exposure,
                    10.0, 80.0, 100.0, 1000.0, 200.0, 25.0,
                ),
            ]);
        }

        public function positionFor(int $userId, int $assetId): ?PositionData
        {
            return null;
        }

        public function analysisFor(int $userId, AssetClass $exposure): AnalysisData
        {
            return AnalysisData::empty();
        }
    });

    app()->instance(ValuationPort::class, new class implements ValuationPort
    {
        public function performancesFor(int $userId, AssetClass $exposure): array
        {
            return [new PerformanceLineData('1M', 'Un mois', '2026-07-29', 900.0, 0.0, 100.0, 11.1)];
        }

        public function evolutionFor(int $userId, AssetClass $exposure): EvolutionData
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

        public function drawdownFor(int $userId, AssetClass $exposure): DrawdownData
        {
            return DrawdownData::empty();
        }
    });

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Analysis',
        'X-Inertia-Partial-Data' => 'performances',
    ];

    $response = $this->get(route('classes.equity.analyse'), $headers);

    $response->assertOk();
    expect($response->json('props.performances.0.key'))->toBe('1M');
});

/**
 * Déménagée depuis `InstrumentsPageTest` (« defers the sector breakdown and loads it on demand »).
 */
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

    $this->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
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

/**
 * Déménagée depuis `InstrumentsPageTest` (« defers the portfolio performances and loads them on
 * demand »).
 */
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

    $this->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
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

/**
 * Déménagée depuis `InstrumentsPageTest` (« diffère le revenu perçu et son historique annuel dans
 * le groupe revenus »).
 */
it('diffère le revenu perçu et son historique annuel dans le groupe revenus', function () {
    $this->travelTo('2026-08-19 10:00:00');
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2025-01-01', 'quantity' => 10, 'unit_price' => 80]);
    Dividend::factory()->create(['asset_id' => $asset->id, 'ex_date' => '2025-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $asset->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.8]);

    $this->actingAs($user)
        ->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('income')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                /** Clôture et cast : voir la note de `InstrumentDetailPageTest` sur `json_encode()`. */
                ->where('income.totalReceived', fn ($v) => (float) $v === 13.0)
                ->where('income.last12Months', fn ($v) => (float) $v === 8.0)
                ->where('income.bySource.dividend', fn ($v) => (float) $v === 13.0)
                ->has('annualIncome', 2)
                ->where('annualIncome.0.year', 2025)
                ->where('annualIncome.1.total', fn ($v) => (float) $v === 8.0)
            )
        );
});
