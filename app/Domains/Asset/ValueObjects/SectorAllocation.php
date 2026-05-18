<?php

namespace App\Domains\Asset\ValueObjects;

use App\Domains\Asset\Enums\Sector;

readonly class SectorAllocation
{
    public function __construct(
        public Sector $sector,
        public float $weight,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sector: $data['sector'] instanceof Sector ? $data['sector'] : Sector::from($data['sector']),
            weight: (float) $data['weight'],
        );
    }
}
