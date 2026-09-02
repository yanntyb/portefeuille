<?php

namespace App\Contexts\PortfolioView\Datas;

readonly class HoldingTrendData
{
    /** @param list<float> $points */
    public function __construct(
        public int $assetId,
        public ?float $changePct,
        public array $points,
    ) {}
}
