<?php

namespace App\Contexts\Wealth\Services;

/**
 * Réconcilie des séries qui n'ont pas la même grille. Les titres se comptent depuis la première
 * transaction, l'immobilier depuis la plus ancienne acquisition, la crypto depuis son premier
 * achat : aucune ne peut servir de grille commune sans tronquer les autres.
 */
class SeriesAligner
{
    /**
     * Variadique et non binaire : le nombre de classes d'actif est celui du registre, pas deux.
     *
     * @param  list<string>  ...$sets
     * @return list<string>
     */
    public function union(array ...$sets): array
    {
        $labels = array_values(array_unique(array_merge(...[[], ...$sets])));
        sort($labels);

        return $labels;
    }

    /**
     * Report d'une série sur une autre grille : chaque label prend la dernière valeur connue de
     * date ≤ à lui, 0 avant le premier point. Jamais d'interpolation — inventer une valeur
     * intermédiaire ferait mentir un tracé dont la donnée est ponctuelle.
     *
     * @param  list<string>  $labels
     * @param  list<string>  $sourceLabels
     * @param  list<float>  $values
     * @return list<float>
     */
    public function onto(array $labels, array $sourceLabels, array $values): array
    {
        $aligned = [];
        $cursor = 0;
        $current = 0.0;

        foreach ($labels as $label) {
            while ($cursor < count($sourceLabels) && $sourceLabels[$cursor] <= $label) {
                $current = $values[$cursor] ?? $current;
                $cursor++;
            }

            $aligned[] = $current;
        }

        return $aligned;
    }

    /**
     * Plusieurs séries empilées sur une même grille. Une série plus courte laisse la fin à zéro :
     * une classe apparue en cours de route n'a rien à ajouter avant son premier point.
     *
     * @param  list<list<float>>  $series
     * @return list<float>
     */
    public function accumulate(array $series, int $length): array
    {
        $totals = array_fill(0, $length, 0.0);

        foreach ($series as $one) {
            foreach ($one as $index => $amount) {
                if ($index < $length) {
                    $totals[$index] += $amount;
                }
            }
        }

        return array_map(fn (float $amount): float => round($amount, 2), $totals);
    }
}
