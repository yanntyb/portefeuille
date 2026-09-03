<?php

namespace App\Contexts\PortfolioView\Pages;

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Price;
use App\Contexts\PortfolioView\Actions\GetInstrumentAnalysis;
use App\Contexts\PortfolioView\Actions\GetInstrumentDetail;
use App\Contexts\PortfolioView\Datas\PriceHistoryData;
use App\Contexts\PortfolioView\Services\ChartStep;
use App\Contexts\PortfolioView\Services\PriceHistoryWindow;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Services\ValuationCalculator;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * La fiche d'un instrument, `/asset/{id}`, une seule pour toutes les expositions. Les dividendes
 * n'apparaissent que si l'exposition porte une origine de revenu (`IncomeSource::forAssetClass`),
 * la même règle que le snapshot. La valorisation se lit au jour puis se ré-échantillonne selon
 * `ChartStep` : le pas est une décision de rendu, il ne traverse pas Valuation.
 */
class AssetPage
{
    public function __construct(
        private GetInstrumentDetail $detail,
        private BuildAssetPerformances $performances,
        private GetAssetDividendHistory $dividends,
        private PriceRepositoryContract $prices,
        private BuildAssetValuationSeries $valuationSeries,
        private ValuationCalculator $calculator,
        private ChartStep $chartStep,
        private GetInstrumentAnalysis $analysis,
    ) {}

    public function for(int $userId, int $assetId): ?PageProps
    {
        $detail = ($this->detail)($userId, $assetId);

        if ($detail === null) {
            return null;
        }

        $sync = [
            'instrument' => $detail,
            'performances' => ($this->performances)($userId, $assetId),
        ];

        if (IncomeSource::forAssetClass($detail->assetClass) !== null) {
            $sync['dividends'] = ($this->dividends)($userId, $assetId);
        }

        return new PageProps(
            sync: $sync,
            deferred: [
                'priceHistory' => new DeferredProp(fn (): PriceHistoryData => $this->priceHistory($assetId), 'default'),
                'valuation' => new DeferredProp(fn (): ValuationSeriesData => $this->valuation($userId, $assetId), 'default'),
                'analysis' => new DeferredProp(fn () => ($this->analysis)($userId, $assetId), 'default'),
            ],
        );
    }

    private function priceHistory(int $assetId): PriceHistoryData
    {
        $prices = $this->prices->forAssetSince($assetId, PriceHistoryWindow::since());

        return new PriceHistoryData(
            labels: $prices->map(fn (Price $price): string => $price->date->format('Y-m-d'))->values()->all(),
            close: $prices->map(fn (Price $price): float => (float) $price->close)->values()->all(),
        );
    }

    private function valuation(int $userId, int $assetId): ValuationSeriesData
    {
        $daily = ($this->valuationSeries)($userId, $assetId, ValuationRange::Max, ValuationGranularity::Day);

        return $this->calculator->windowAndAggregate($daily, null, $this->chartStep->for($daily->labels));
    }
}
