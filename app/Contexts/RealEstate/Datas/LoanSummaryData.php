<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Résumé du prêt d'un bien : conditions d'origine et situation actuelle. */
readonly class LoanSummaryData implements JsonSerializable
{
    public function __construct(
        public float $principal,
        public float $annualRate,
        public int $termMonths,
        public string $startDate,
        public float $monthlyInsurance,
        public float $monthlyPayment,
        public float $remainingPrincipal,
        public float $totalCost,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'principal' => $this->principal,
            'annualRate' => $this->annualRate,
            'termMonths' => $this->termMonths,
            'startDate' => $this->startDate,
            'monthlyInsurance' => $this->monthlyInsurance,
            'monthlyPayment' => $this->monthlyPayment,
            'remainingPrincipal' => $this->remainingPrincipal,
            'totalCost' => $this->totalCost,
        ];
    }
}
