<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Actions\GetRealEstateCashInvested;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Datas\PropertyOverviewData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\RealEstatePort;

class RealEstateFinancials implements RealEstatePort
{
    public function __construct(
        private GetRealEstateOverview $overview,
        private GetRealEstateCashInvested $cashInvested,
        private BuildRealEstateSeries $series,
    ) {}

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        return new ClassSnapshotData(
            value: ($this->overview)($userId)->totalNetWorth,
            invested: ($this->cashInvested)($userId),
        );
    }

    public function seriesFor(int $userId): ClassSeriesData
    {
        $series = ($this->series)($userId);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $series->netWorth,
            invested: $series->invested,
        );
    }

    public function monthlyNetFor(int $userId): float
    {
        $properties = ($this->overview)($userId)->properties;

        return round(array_sum(array_map(
            fn (PropertyOverviewData $property): float => $property->monthlyCashFlow,
            $properties,
        )), 2);
    }
}
