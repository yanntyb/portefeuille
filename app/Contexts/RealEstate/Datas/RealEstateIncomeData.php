<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Ce que le parc a rapporté : les douze derniers mois, puis chaque année depuis la première acquisition. */
readonly class RealEstateIncomeData implements JsonSerializable
{
    /** @param list<RealEstateIncomeYearData> $years */
    public function __construct(
        public float $rents12m,
        public float $expenses12m,
        public float $loanPayments12m,
        public float $net12m,
        public array $years,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'rents12m' => $this->rents12m,
            'expenses12m' => $this->expenses12m,
            'loanPayments12m' => $this->loanPayments12m,
            'net12m' => $this->net12m,
            'years' => array_map(
                fn (RealEstateIncomeYearData $year): array => $year->jsonSerialize(),
                $this->years,
            ),
        ];
    }
}
