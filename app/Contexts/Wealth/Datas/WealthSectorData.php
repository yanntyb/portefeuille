<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Un secteur du patrimoine : ce qu'il pèse en euros, et la part qu'il en occupe. */
readonly class WealthSectorData implements JsonSerializable
{
    public function __construct(
        public string $label,
        public float $value,
        public float $pct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'pct' => $this->pct,
        ];
    }
}
