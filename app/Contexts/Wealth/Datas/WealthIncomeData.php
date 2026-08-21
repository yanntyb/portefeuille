<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Ce que le patrimoine laisse chaque mois, et d'où ça vient. */
readonly class WealthIncomeData implements JsonSerializable
{
    public function __construct(
        public float $monthlyTotal,
        public float $monthlyDividends,
        public float $monthlyRentalNet,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'monthlyTotal' => $this->monthlyTotal,
            'monthlyDividends' => $this->monthlyDividends,
            'monthlyRentalNet' => $this->monthlyRentalNet,
        ];
    }
}
