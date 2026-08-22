<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Une origine du revenu mensuel : d'où il vient, et combien il laisse. */
readonly class IncomeOriginData implements JsonSerializable
{
    public function __construct(
        public string $label,
        public float $amount,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'amount' => $this->amount,
        ];
    }
}
