<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * La valeur d'un périmètre dans le temps, comparée à ce qui y a été mis. Une seule courbe, là où
 * `EvolutionData` en porte une par actif : l'enveloppe se lit en bloc, et sa série ne sait pas se
 * détailler par instrument — `BuildEvolutionSeries` filtre après son cache, par classe seulement.
 */
readonly class ExposureSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $value
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $value,
        public array $invested,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'value' => $this->value,
            'invested' => $this->invested,
        ];
    }
}
