<?php

namespace App\Contexts\InstrumentView\Datas;

use App\Contexts\Market\Enums\InstrumentType;

readonly class InstrumentMetaData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public ?float $lastPrice,
        public ?string $lastPriceDate,
    ) {}
}
