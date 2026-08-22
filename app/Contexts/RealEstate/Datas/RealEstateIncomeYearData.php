<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Une année de revenus locatifs du parc : ce qui est entré, ce qui est sorti, ce qui reste. */
readonly class RealEstateIncomeYearData implements JsonSerializable
{
    public function __construct(
        public int $year,
        public float $rents,
        public float $expenses,
        public float $loanPayments,
        public float $net,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'year' => $this->year,
            'rents' => $this->rents,
            'expenses' => $this->expenses,
            'loanPayments' => $this->loanPayments,
            'net' => $this->net,
        ];
    }
}
