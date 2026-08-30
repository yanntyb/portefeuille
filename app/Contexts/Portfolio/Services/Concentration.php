<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\ConcentrationData;

/**
 * La concentration d'un portefeuille : le poids de ses plus grosses positions, et l'indice de
 * Herfindahl-Hirschman, somme des carrés des poids — 1 pour une position unique, 1/n pour n
 * positions égales.
 *
 * Prend des valeurs et non des poids : la normalisation vit ici, pour que l'appelant n'ait pas à
 * diviser avant et que la règle d'exclusion n'ait qu'un site.
 */
class Concentration
{
    /** @param  list<?float>  $values  Valeurs de marché des positions, ordre indifférent. */
    public function of(array $values): ConcentrationData
    {
        /** Une position sans cours connu, nulle ou négative n'est pas une exposition à mesurer. */
        $kept = array_values(array_filter(
            $values,
            fn (?float $value): bool => $value !== null && $value > 0.0,
        ));

        $total = array_sum($kept);

        if ($total <= 0.0) {
            return ConcentrationData::empty();
        }

        $weights = array_map(fn (float $value): float => $value / $total, $kept);
        rsort($weights);

        return new ConcentrationData(
            top1: $this->topN($weights, 1),
            top3: $this->topN($weights, 3),
            top5: $this->topN($weights, 5),
            hhi: array_sum(array_map(fn (float $weight): float => $weight ** 2, $weights)),
        );
    }

    /**
     * Le cumul des `$n` plus gros poids, en pourcentage. Moins de `$n` positions donne 100 %, ce
     * qui est la réponse juste et non une valeur manquante.
     *
     * @param  list<float>  $weights  Décroissants.
     */
    private function topN(array $weights, int $n): float
    {
        return round(array_sum(array_slice($weights, 0, $n)) * 100, 2);
    }
}
