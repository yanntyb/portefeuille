<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use Illuminate\Support\Carbon;

/** Un détachement tel que le contexte Marché le publie, avant tout croisement avec une position. */
readonly class DividendRecordData
{
    public function __construct(
        public int $assetId,
        public Carbon $exDate,
        public float $amountPerShare,
    ) {}
}
