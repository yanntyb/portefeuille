<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use Illuminate\Support\Carbon;

/** Un mouvement de position, réduit à ce dont le calcul a besoin : un sens et une quantité. */
readonly class PositionRecordData
{
    public function __construct(
        public int $assetId,
        public int $walletId,
        public Carbon $date,
        public bool $isSell,
        public float $quantity,
    ) {}
}
