<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Income\Actions\GetAnnualIncome;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Datas\AnnualIncomeData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\IncomeOverviewData;
use App\Contexts\MarketView\Datas\IncomeYearData;
use App\Contexts\MarketView\Ports\IncomePort;

/**
 * L'origine du revenu se déduit de l'exposition, ici et non dans la page : c'est la même règle que
 * `BuildMarketViewSnapshot` applique à son gate de dividendes.
 */
class IncomeTotals implements IncomePort
{
    public function __construct(
        private GetIncomeSummary $summary,
        private GetAnnualIncome $annual,
    ) {}

    public function supportsExposure(AssetClass $exposure): bool
    {
        return IncomeSource::forAssetClass($exposure) !== null;
    }

    public function summaryFor(int $userId, AssetClass $exposure): IncomeOverviewData
    {
        $source = IncomeSource::forAssetClass($exposure);

        if ($source === null) {
            return IncomeOverviewData::empty();
        }

        $summary = ($this->summary)($userId, $source);

        return new IncomeOverviewData(
            totalReceived: $summary->totalReceived,
            last12Months: $summary->last12Months,
            estimatedAnnual: $summary->estimatedAnnual,
            bySource: $summary->bySource,
        );
    }

    /** @return list<IncomeYearData> */
    public function annualFor(int $userId, AssetClass $exposure): array
    {
        $source = IncomeSource::forAssetClass($exposure);

        if ($source === null) {
            return [];
        }

        return array_map(
            fn (AnnualIncomeData $year): IncomeYearData => new IncomeYearData(
                year: $year->year,
                total: $year->total,
                bySource: $year->bySource,
            ),
            ($this->annual)($userId, $source),
        );
    }
}
