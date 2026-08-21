<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/**
 * Résumé du prêt d'un bien : conditions d'origine et situation actuelle. Les champs de situation
 * — échéances réglées, date de fin, capital remboursé, intérêts payés et à venir — ne se
 * déduisent que de l'échéancier ; ils arrivent avec la fiche pour que la section n'attende pas
 * la prop différée qui porte le détail mois par mois.
 */
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
        public string $endDate,
        public int $monthsPaid,
        public float $principalRepaid,
        public float $interestPaid,
        public float $interestRemaining,
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
            'endDate' => $this->endDate,
            'monthsPaid' => $this->monthsPaid,
            'principalRepaid' => $this->principalRepaid,
            'interestPaid' => $this->interestPaid,
            'interestRemaining' => $this->interestRemaining,
        ];
    }
}
