<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Un loyer mensuel prévu et effectif. `expected = 0` marque la vacance, `effective < expected` un impayé. */
readonly class RentMonthData implements JsonSerializable
{
    public function __construct(
        public string $month,
        public float $expected,
        public float $effective,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'month' => $this->month,
            'expected' => $this->expected,
            'effective' => $this->effective,
        ];
    }
}
