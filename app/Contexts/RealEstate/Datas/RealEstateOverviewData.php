<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Carte immobilière du tableau de bord : patrimoine net par bien et totaux. */
readonly class RealEstateOverviewData implements JsonSerializable
{
    /** @param list<PropertyOverviewData> $properties */
    public function __construct(
        public array $properties,
        public float $totalValue,
        public float $totalRemaining,
        public float $totalNetWorth,
        public float $totalInvested,
        public float $totalMonthlyCashFlow,
    ) {}

    public static function empty(): self
    {
        return new self([], 0.0, 0.0, 0.0, 0.0, 0.0);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'properties' => array_map(fn (PropertyOverviewData $property): array => $property->jsonSerialize(), $this->properties),
            'totalValue' => $this->totalValue,
            'totalRemaining' => $this->totalRemaining,
            'totalNetWorth' => $this->totalNetWorth,
            'totalInvested' => $this->totalInvested,
            'totalMonthlyCashFlow' => $this->totalMonthlyCashFlow,
        ];
    }
}
