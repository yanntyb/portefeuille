<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class PositionData implements JsonSerializable
{
    public function __construct(
        public float $quantity,
        public ?float $avgCost,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'quantity' => $this->quantity,
            'avgCost' => $this->avgCost,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
