<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

/** Position courante sur un actif, réduite à ce qu'un rendement sur coût demande. */
readonly class PositionSnapshotData
{
    public function __construct(
        public float $quantity,
        public ?float $avgCost,
    ) {}
}
