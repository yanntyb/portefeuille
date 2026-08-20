<?php

namespace App\Contexts\RealEstate\Datas;

/** Données financières d'un bien locatif pour le calcul des indicateurs de rentabilité. */
readonly class PropertyFinancialsData
{
    public function __construct(
        public float $acquisitionPrice,
        public float $acquisitionFees,
        public float $currentMonthlyRent,
        public float $rents12m,
        public float $expenses12m,
        public float $loanPayments12m,
        public float $borrowedPrincipal,
        public float $remainingPrincipal,
        public float $currentValue,
    ) {}
}
