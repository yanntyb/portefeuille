<?php

namespace App\Contexts\RealEstate\Datas;

/** Un bail de location avec début, fin optionnelle, et loyer mensuel. */
readonly class LeaseTermData
{
    public function __construct(
        public string $start,
        public ?string $end,
        public float $monthlyRent,
    ) {}
}
