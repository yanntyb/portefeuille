<?php

namespace App\Contexts\Market\Datas;

/**
 * Les extrêmes de l'année boursière et la distance du dernier cours à son sommet, négative ou
 * nulle : situer le prix dans son propre historique, sans le comparer à quoi que ce soit d'autre.
 */
readonly class FiftyTwoWeekData
{
    public function __construct(
        public float $high,
        public float $low,
        public ?float $gapPct,
    ) {}
}
