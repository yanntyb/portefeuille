<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Une échéance de prêt. `month` porte le premier jour du mois d'échéance. */
readonly class AmortizationLineData implements JsonSerializable
{
    public function __construct(
        public string $month,
        public float $payment,
        public float $interest,
        public float $principal,
        public float $insurance,
        public float $remaining,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'month' => $this->month,
            'payment' => $this->payment,
            'interest' => $this->interest,
            'principal' => $this->principal,
            'insurance' => $this->insurance,
            'remaining' => $this->remaining,
        ];
    }
}
