<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Charges d'un bien sur une année, ventilées par catégorie. */
readonly class ExpenseYearData implements JsonSerializable
{
    /** @param list<array{category: string, label: string, amount: float}> $byCategory Triée par montant décroissant. */
    public function __construct(
        public int $year,
        public array $byCategory,
        public float $total,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'year' => $this->year,
            'byCategory' => $this->byCategory,
            'total' => $this->total,
        ];
    }
}
