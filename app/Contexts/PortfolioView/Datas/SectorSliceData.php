<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Une part de la répartition sectorielle du portefeuille, telle que la page liste l'affiche.
 *
 * Distincte de `SectorWeightData`, qui pèse les secteurs d'un actif isolé sur sa fiche : celle-ci
 * porte une valeur en euros et une couleur, dont la fiche n'a que faire.
 */
readonly class SectorSliceData implements JsonSerializable
{
    /**
     * @param  float  $pct  part du total, en pourcentage
     */
    public function __construct(
        public string $label,
        public float $value,
        public float $pct,
        public string $color,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'pct' => $this->pct,
            'color' => $this->color,
        ];
    }
}
