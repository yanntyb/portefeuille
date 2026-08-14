<?php

namespace App\Contexts\InstrumentView\Datas;

readonly class CatalogTrendData
{
    /** @param list<float> $points */
    public function __construct(
        public int $assetId,
        public ?float $changePct,
        public array $points,
    ) {}
}
