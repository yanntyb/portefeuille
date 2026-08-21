<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Le patrimoine dans le temps, les deux classes sur une grille commune : le graphe les empile,
 * donc leurs indices doivent se correspondre un à un.
 */
readonly class WealthSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $securities
     * @param  list<float>  $realEstate
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $securities,
        public array $realEstate,
        public array $invested,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'securities' => $this->securities,
            'realEstate' => $this->realEstate,
            'invested' => $this->invested,
        ];
    }
}
