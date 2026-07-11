<?php

namespace App\Contexts\Valuation\Datas;

use Illuminate\Support\Carbon;

readonly class TransactionRecordData
{
    public function __construct(
        public Carbon $date,
        public int $assetId,
        public bool $isSell,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
    ) {}
}
