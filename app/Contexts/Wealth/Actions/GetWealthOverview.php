<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\AssetClassData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Ports\HoldingsPort;
use App\Contexts\Wealth\Ports\RealEstatePort;

/** Le patrimoine d'un utilisateur, toutes classes d'actif confondues. */
class GetWealthOverview
{
    public function __construct(
        private HoldingsPort $holdings,
        private RealEstatePort $realEstate,
    ) {}

    public function __invoke(int $userId): WealthOverviewData
    {
        $securities = $this->holdings->snapshotFor($userId);
        $realEstate = $this->realEstate->snapshotFor($userId);

        $total = new ClassSnapshotData(
            value: $securities->value + $realEstate->value,
            invested: $securities->invested + $realEstate->invested,
        );

        $totals = AssetClassData::from($total);

        return new WealthOverviewData(
            totalValue: $totals->value,
            totalInvested: $totals->invested,
            totalGain: $totals->gain,
            totalGainPct: $totals->gainPct,
            securities: AssetClassData::from($securities),
            realEstate: AssetClassData::from($realEstate),
        );
    }
}
