<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Actions\GetRealEstateCashInvested;
use App\Contexts\RealEstate\Actions\GetRealEstateCashReturned;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Datas\PropertyOverviewData;
use App\Contexts\Wealth\Datas\ClassSectorData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\AssetClassPort;

/** Le parc immobilier : son patrimoine net, le cash qu'il a coûté, et ce qu'il laisse chaque mois. */
class RealEstateClass implements AssetClassPort
{
    public function key(): string
    {
        return 'realEstate';
    }

    public function label(): string
    {
        return 'Immobilier';
    }

    public function href(): string
    {
        return '/properties';
    }

    public function color(): string
    {
        return 'realEstate';
    }

    public function incomeLabel(): ?string
    {
        return 'Locatif net';
    }

    public function __construct(
        private GetRealEstateOverview $overview,
        private GetRealEstateCashInvested $cashInvested,
        private GetRealEstateCashReturned $cashReturned,
        private BuildRealEstateSeries $series,
    ) {}

    /**
     * Le réalisé est le miroir de la mise : `GetRealEstateCashInvested` compte les mois
     * déficitaires, `GetRealEstateCashReturned` les excédentaires. Aucun mois ne peut peupler les
     * deux, sans quoi le loyer encaissé sortirait du patrimoine et un bien à cash-flow positif
     * afficherait moins de gain qu'un bien déficitaire de même rendement.
     */
    public function snapshotFor(int $userId): ClassSnapshotData
    {
        return new ClassSnapshotData(
            value: ($this->overview)($userId)->totalNetWorth,
            invested: ($this->cashInvested)($userId),
            realized: ($this->cashReturned)($userId),
        );
    }

    /**
     * Une tranche unique à son nom : un bien n'a pas de secteur boursier, mais il pèse dans la
     * ventilation du patrimoine et doit s'y montrer.
     *
     * @return list<ClassSectorData>
     */
    public function sectorSlicesFor(int $userId): array
    {
        return [new ClassSectorData(label: $this->label(), value: ($this->overview)($userId)->totalNetWorth)];
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

    public function monthlyIncomeFor(int $userId): float
    {
        $properties = ($this->overview)($userId)->properties;

        return round(array_sum(array_map(
            fn (PropertyOverviewData $property): float => $property->monthlyCashFlow,
            $properties,
        )), 2);
    }
}
