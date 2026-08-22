<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Une ligne de la section Rentabilité : un bien et ses indicateurs, pour les comparer entre eux. */
readonly class PropertyProfitabilityData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public PropertyMetricsData $metrics,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'metrics' => $this->metrics->jsonSerialize(),
        ];
    }
}
