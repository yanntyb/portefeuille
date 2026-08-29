<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\MarketView\Datas\AssetLineData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\HoldingRowData;
use App\Contexts\MarketView\Datas\IncomeOverviewData;
use App\Contexts\MarketView\Datas\IncomeYearData;
use App\Contexts\MarketView\Datas\PerformanceLineData;
use App\Contexts\MarketView\Datas\PortfolioSummaryData;
use App\Contexts\MarketView\Datas\SectorSliceData;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use App\Http\Middleware\HandleInertiaRequests;

it('serves a list page for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetClass/Index')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel())
            ->where('assetClass.hasSectors', $class->hasSectors())
            ->where('assetClass.hasIncome', $class === AssetClass::Equity));
})->with(AssetClass::cases());

/**
 * `sectorBreakdown` et `income` voyagent en props différées : absentes de `props` à la première
 * réponse, elles n'apparaissent que dans `deferredProps`, comme le vérifie déjà
 * `CryptoControllerTest` pour `tendances`/`performances`/`evolution`.
 */
it('offers sectors and income on equity alone', function () {
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];

    $equity = $this->get(route('classes.equity'), $headers);
    $equity->assertOk();
    expect($equity->json('deferredProps'))->toHaveKeys(['secteurs', 'revenus']);

    $crypto = $this->get(route('classes.crypto'), $headers);
    $crypto->assertOk();
    expect($crypto->json('deferredProps'))
        ->not->toHaveKey('secteurs')
        ->not->toHaveKey('revenus');
});

/**
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
    });

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Index',
        'X-Inertia-Partial-Data' => 'sectorBreakdown,income,annualIncome',
    ];

    $response = $this->get(route('classes.equity'), $headers);

    $response->assertOk();
    expect($response->json('props.sectorBreakdown.0.label'))->toBe('Technologie')
        ->and($response->json('props.income.totalReceived'))->toEqual(13.0)
        ->and($response->json('props.annualIncome.0.year'))->toBe(2026);
});

/**
 * Même démonstration pour l'aperçu, les performances et l'évolution : la page se sert entièrement
 * de ports factices, donc elle ne lit plus rien de Portfolio ni de Valuation.
 */
it('sert l\'aperçu, les performances et l\'évolution par leurs seuls ports', function () {
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
    });

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Index',
        'X-Inertia-Partial-Data' => 'overview,performances,evolutionSeries',
    ];

    $response = $this->get(route('classes.equity'), $headers);

    $response->assertOk();
    expect($response->json('props.overview.totalValue'))->toEqual(1000.0)
        ->and($response->json('props.overview.holdings.0.typeLabel'))->toBe(InstrumentType::Stock->getLabel())
        ->and($response->json('props.performances.0.key'))->toBe('1M')
        ->and($response->json('props.evolutionSeries.perAsset.0.name'))->toBe('ACME');
});
