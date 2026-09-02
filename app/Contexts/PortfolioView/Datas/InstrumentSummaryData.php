<?php

namespace App\Contexts\PortfolioView\Datas;

use App\Contexts\Market\Enums\InstrumentType;

readonly class InstrumentSummaryData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
    ) {}
}
