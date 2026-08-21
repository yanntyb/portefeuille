<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;

interface RealEstatePort
{
    /** Patrimoine net et cash sorti du parc immobilier. */
    public function snapshotFor(int $userId): ClassSnapshotData;

    /** Patrimoine net et cash sorti dans le temps, sur la grille du fournisseur. */
    public function seriesFor(int $userId): ClassSeriesData;

    /** Cash-flow locatif net mensuel, après charges et échéances. */
    public function monthlyNetFor(int $userId): float;
}
