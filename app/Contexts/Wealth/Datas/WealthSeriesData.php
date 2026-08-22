<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Le patrimoine dans le temps, toutes les classes sur une grille commune : le graphe les empile,
 * donc leurs indices doivent se correspondre un à un, et `invested` est déjà leur somme.
 */
readonly class WealthSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<ClassValuesData>  $classes
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $classes,
        public array $invested,
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
            'classes' => array_map(fn (ClassValuesData $class): array => $class->jsonSerialize(), $this->classes),
            'invested' => $this->invested,
        ];
    }
}
