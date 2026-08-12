<?php

namespace App\Contexts\Valuation\Datas;

/**
 * Résultat du calcul de performance sur une fenêtre : les grandeurs intermédiaires
 * du rendement, pas seulement le pourcentage.
 */
readonly class PerformanceWindowData
{
    public function __construct(
        public string $startDate,
        public float $valueStart,
        public float $contributions,
        public float $pnl,
        public float $pct,
    ) {}
}
