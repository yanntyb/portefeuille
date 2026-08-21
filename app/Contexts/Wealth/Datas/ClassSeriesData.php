<?php

namespace App\Contexts\Wealth\Datas;

/** Une classe d'actif dans le temps, sur sa propre grille de labels. */
readonly class ClassSeriesData
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

    public static function empty(): self
    {
        return new self([], [], []);
    }
}
