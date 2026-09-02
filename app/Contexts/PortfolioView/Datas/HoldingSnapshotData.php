<?php

namespace App\Contexts\PortfolioView\Datas;

readonly class HoldingSnapshotData
{
    public function __construct(
        public int $assetId,
        public float $quantity,
        public ?float $avgCost,
    ) {}
}
