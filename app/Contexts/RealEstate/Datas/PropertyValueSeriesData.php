<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/**
 * Historique mensuel d'un bien : sa valeur estimée face au capital restant dû, l'écart entre les
 * deux étant son patrimoine net. Les trois tableaux partagent le même index.
 */
readonly class PropertyValueSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels  Premier jour de chaque mois, du plus ancien au plus récent.
     * @param  list<float>  $values
     * @param  list<float>  $remaining
     */
    public function __construct(
        public array $labels,
        public array $values,
        public array $remaining,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'values' => $this->values,
            'remaining' => $this->remaining,
        ];
    }
}
