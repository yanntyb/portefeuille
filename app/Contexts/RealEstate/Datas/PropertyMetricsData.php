<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Indicateurs de rentabilité d'un bien locatif, tous avant impôt. Ratios en fraction arrondis à 4 décimales. */
readonly class PropertyMetricsData implements JsonSerializable
{
    public function __construct(
        public ?float $grossYield,
        public ?float $netYield,
        public float $annualCashFlow,
        public ?float $cashOnCash,
        public ?float $ltv,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'grossYield' => $this->grossYield,
            'netYield' => $this->netYield,
            'annualCashFlow' => $this->annualCashFlow,
            'cashOnCash' => $this->cashOnCash,
            'ltv' => $this->ltv,
        ];
    }
}
