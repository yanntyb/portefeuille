<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * L'évolution d'une exposition : une abscisse commune, et une courbe par actif détenu.
 *
 * L'abscisse reste celle de tout le portefeuille, y compris quand les courbes se limitent à une
 * exposition — c'est ce que produit `BuildEvolutionSeries`, qui filtre après son cache.
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
