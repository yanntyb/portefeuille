<?php

namespace App\Contexts\Valuation\Datas;

readonly class PriceRecordData
{
    public function __construct(
        public int $assetId,
        public string $date,
        public float $close,
    ) {}
}
