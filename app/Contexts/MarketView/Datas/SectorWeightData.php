<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

readonly class SectorWeightData implements JsonSerializable
{
    public function __construct(
        public string $label,
        public float $weight,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'weight' => $this->weight,
        ];
    }
}
