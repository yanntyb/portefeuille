<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\AssetClassData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;

/** Le patrimoine d'un utilisateur, toutes classes d'actif confondues. */
class GetWealthOverview
{
    public function __construct(private AssetClassRegistry $classes) {}

    public function __invoke(int $userId): WealthOverviewData
    {
        $lines = [];
        $value = 0.0;
        $invested = 0.0;
        $realized = 0.0;

        foreach ($this->classes->all() as $class) {
            $snapshot = $class->snapshotFor($userId);
            $lines[] = AssetClassData::from($class, $snapshot);
            $value += $snapshot->value;
            $invested += $snapshot->invested;
            $realized += $snapshot->realized;
        }

        $total = new ClassSnapshotData(value: $value, invested: $invested);

        return new WealthOverviewData(
            totalValue: round($value, 2),
            totalInvested: round($invested, 2),
            totalGain: AssetClassData::gainOf($total),
            totalGainPct: AssetClassData::gainPctOf($total),
            totalRealizedGain: round($realized, 2),
            classes: $lines,
        );
    }
}
