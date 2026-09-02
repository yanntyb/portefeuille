<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * L'évolution d'un périmètre : une abscisse commune, et une courbe par actif détenu.
 *
 * Pour une exposition, l'abscisse reste celle de tout le portefeuille — `BuildEvolutionSeries` y
 * filtre après son cache. Pour une enveloppe, elle est celle de l'enveloppe seule, qui se découpe
 * avant.
 */
readonly class EvolutionData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<AssetLineData>  $perAsset
     */
    public function __construct(
        public array $labels,
        public array $perAsset,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'perAsset' => array_map(
                fn (AssetLineData $line): array => $line->jsonSerialize(),
                $this->perAsset,
            ),
        ];
    }
}
