<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\MarketView\Datas\AssetValuationData;
use App\Contexts\MarketView\Datas\DividendHistoryData;
use App\Contexts\MarketView\Datas\DividendLineData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\IncomeOverviewData;
use App\Contexts\MarketView\Datas\PerformanceLineData;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\ValuationPort;
use App\Http\Middleware\HandleInertiaRequests;

it('serves one address for every asset, whatever its exposure', function (InstrumentType $type) {
    $instrument = Instrument::factory()->create(['type' => $type]);

    $this->get(route('assets.show', $instrument->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Asset/Show'));
})->with([InstrumentType::Stock, InstrumentType::ETF, InstrumentType::Commodity, InstrumentType::Crypto]);

it('carries dividends on equity and withholds them elsewhere', function () {
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);

    $this->get(route('assets.show', $stock->id))
        ->assertInertia(fn ($page) => $page->has('dividends'));

    $this->get(route('assets.show', $gold->id))
        ->assertInertia(fn ($page) => $page->missing('dividends'));
});

it('answers 404 on an unknown asset', function () {
    $this->get(route('assets.show', 999999))->assertNotFound();
});

/**
 * La fiche ne lit ni Valuation ni Income : des ports factices suffisent à la servir en entier,
 * prop différée comprise.
 */
it('sert les performances, la valorisation et les détachements par leurs seuls ports', function () {
    app()->instance(ValuationPort::class, new class implements ValuationPort
    {
        public function performancesFor(int $userId, AssetClass $exposure): array
        {
            return [];
        }

        public function evolutionFor(int $userId, AssetClass $exposure): EvolutionData
        {
            return new EvolutionData([], []);
        }

        public function assetPerformancesFor(int $userId, int $assetId): array
        {
            return [new PerformanceLineData('1M', 'Un mois', '2026-07-29', 90.0, 0.0, 10.0, 11.1)];
        }

        public function assetSeriesFor(int $userId, int $assetId): AssetValuationData
        {
            return new AssetValuationData(['2026-08-01'], [100.0], [80.0], [10.0]);
        }
    });

    app()->instance(IncomePort::class, new class implements IncomePort
    {
        public function supportsExposure(AssetClass $exposure): bool
        {
            return true;
        }

        public function summaryFor(int $userId, AssetClass $exposure): IncomeOverviewData
        {
            return IncomeOverviewData::empty();
        }

        public function annualFor(int $userId, AssetClass $exposure): array
        {
            return [];
        }

        public function assetHistoryFor(int $userId, int $assetId): DividendHistoryData
        {
            return new DividendHistoryData(
                [new DividendLineData($assetId, '2026-03-05', 10.0, 0.8, 8.0)],
                8.0, 8.0, 8.0, 1.0,
            );
        }
    });

    $instrument = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    $this->get(route('assets.show', $instrument->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('performances.0.key', '1M')
            ->where('dividends.receipts.0.exDate', '2026-03-05')
            ->etc());

    $partial = $this->get(route('assets.show', $instrument->id), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Asset/Show',
        'X-Inertia-Partial-Data' => 'valuation',
    ]);

    expect($partial->json('props.valuation.prices'))->toEqual([10.0]);
});
