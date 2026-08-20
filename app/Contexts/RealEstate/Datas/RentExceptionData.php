<?php

namespace App\Contexts\RealEstate\Datas;

/** Une exception au loyer prévu pour un mois donné (impayé, remise, etc.). */
readonly class RentExceptionData
{
    public function __construct(
        public string $month,
        public float $amountOverride,
    ) {}
}
