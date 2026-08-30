<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\MarketView\Datas\AnalysisData;
use App\Contexts\MarketView\Datas\AssetLineData;
use App\Contexts\MarketView\Datas\AssetValuationData;
use App\Contexts\MarketView\Datas\DrawdownData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\HoldingRowData;
use App\Contexts\MarketView\Datas\PerformanceLineData;
use App\Contexts\MarketView\Datas\PortfolioSummaryData;
use App\Contexts\MarketView\Datas\PositionData;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use App\Http\Middleware\HandleInertiaRequests;

it('serves a list page for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetClass/Index')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel()));
})->with(AssetClass::cases());

/**
 * Même démonstration pour l'aperçu et l'évolution : la page se sert entièrement de ports
 * factices, donc elle ne lit plus rien de Portfolio ni de Valuation.
 *
 * Les volets secteurs, performances et revenus ont déménagé vers la page analyse : les
 * assertions équivalentes vivent désormais dans `AssetClassAnalysisControllerTest`
 * (« serves the hasSectors/hasIncome flags for every exposure », « offers sectors and income on
 * equity alone », « sert les sections secteur et revenus par leurs seuls ports » et « sert les
 * performances par leur seul port »).
 */
it('sert l\'aperçu et l\'évolution par leurs seuls ports', function () {
    app()->instance(PortfolioOverviewPort::class, new class implements PortfolioOverviewPort
    {
        public function overviewFor(int $userId, AssetClass $exposure): PortfolioSummaryData
        {
            return new PortfolioSummaryData(1000.0, 800.0, 200.0, 25.0, 0.0, [
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
        'X-Inertia-Partial-Component' => 'AssetClass/Index',
        'X-Inertia-Partial-Data' => 'overview,evolutionSeries',
    ];

    $response = $this->get(route('classes.equity'), $headers);

    $response->assertOk();
    expect($response->json('props.overview.totalValue'))->toEqual(1000.0)
        ->and($response->json('props.overview.holdings.0.typeLabel'))->toBe(InstrumentType::Stock->getLabel())
        ->and($response->json('props.evolutionSeries.perAsset.0.name'))->toBe('ACME');
});
