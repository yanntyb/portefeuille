<?php

namespace App\Contexts\Wealth\Datas;

/**
 * Ce qu'une classe d'actif expose à un secteur, en euros. Sans part : une classe ignore le
 * patrimoine dans lequel elle s'inscrit, et le pourcentage se calcule au moment de l'agrégation.
 */
readonly class ClassSectorData
{
    public function __construct(
        public string $label,
        public float $value,
    ) {}
}
