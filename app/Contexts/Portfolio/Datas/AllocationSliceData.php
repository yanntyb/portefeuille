<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class AllocationSliceData implements JsonSerializable
{
    public function __construct(
        public string $label,
        public float $value,
        public float $pct,
        public string $color,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'pct' => $this->pct,
            'color' => $this->color,
        ];
    }
}
