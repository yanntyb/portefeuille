<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * La performance d'une fenêtre glissante — le portefeuille d'une exposition, ou un actif seul.
 */
readonly class PerformanceLineData implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $startDate,
        public float $valueStart,
        public float $contributions,
        public float $gain,
        public float $pct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'startDate' => $this->startDate,
            'valueStart' => $this->valueStart,
            'contributions' => $this->contributions,
            'gain' => $this->gain,
            'pct' => $this->pct,
        ];
    }
}
