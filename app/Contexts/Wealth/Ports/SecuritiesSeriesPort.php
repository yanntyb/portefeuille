<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;

interface SecuritiesSeriesPort
{
    /** Valeur et investi des titres dans le temps, sur la grille du fournisseur. */
    public function seriesFor(int $userId): ClassSeriesData;
}
