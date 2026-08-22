<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Une ligne de la carte immobilière du tableau de bord : un bien, sa valeur et son patrimoine net. */
readonly class PropertyOverviewData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public float $currentValue,
        public float $remainingPrincipal,
        public float $netWorth,
        public float $monthlyCashFlow,
        /** Cash sorti pour ce bien : apport et mois déficitaires, jamais le capital remboursé. */
        public float $invested,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currentValue' => $this->currentValue,
            'remainingPrincipal' => $this->remainingPrincipal,
            'netWorth' => $this->netWorth,
            'monthlyCashFlow' => $this->monthlyCashFlow,
            'invested' => $this->invested,
        ];
    }
}
