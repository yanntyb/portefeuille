<?php

namespace App\Contexts\MarketView\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;

readonly class InstrumentMetaData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public AssetClass $assetClass,
        public ?float $lastPrice,
        public ?string $lastPriceDate,
    ) {}
}
