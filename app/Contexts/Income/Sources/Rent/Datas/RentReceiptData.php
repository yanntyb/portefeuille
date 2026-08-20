<?php

namespace App\Contexts\Income\Sources\Rent\Datas;

/** Un loyer encaissé pour un mois donné, sur un bien identifié par son nom. */
readonly class RentReceiptData
{
    public function __construct(
        public string $month,
        public float $amount,
        public string $propertyName,
    ) {}
}
