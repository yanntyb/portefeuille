<?php

namespace App\Contexts\Market\Services;

use App\Contexts\Market\Datas\BasketIndexData;

/**
 * Un panier d'instruments ramené à une série unique, base 100, à pondération courante. L'indice se
 * chaîne séance après séance : le rendement du jour est la moyenne des rendements des instruments
 * cotés ce jour-là comme la veille, pondérée par leur place dans le panier.
 *
 * Le chaînage sert à deux choses. Il rend l'indice neutre aux apports et aux retraits — la
 * valorisation de la poche, elle, monterait sur un simple versement et signerait un faux
 * plus-haut. Et il laisse l'indice courir aussi loin que le plus ancien instrument : un titre
 * acheté le mois dernier entre dans le panier au jour où il commence à coter, sans tronquer
 * l'histoire des autres.
 *
 * Une fois entré, un instrument ne quitte plus le panier : une séance qu'il manque — place
 * fermée, publication en retard — se comble par report de son dernier cours. C'est la différence
 * entre « pas encore coté » et « pas coté ce jour-là », que le seul trou dans la série ne dit pas.
 * Sans ce report, une séance où seule une partie des places a publié renormaliserait les poids sur
 * elles et prêterait leur mouvement au panier entier — en bout de série, un faux plus-haut.
 */
class BasketIndex
{
    /** La base de l'indice, celle de tous les indices boursiers. */
    private const BASE = 100.0;

    /**
     * @param  array<int|string, array<string, float>>  $closesByKey  Clôtures par date, par instrument.
     * @param  array<int|string, float>  $weightsByKey  Poids courants ; un instrument sans poids sort du panier.
     */
    public function of(array $closesByKey, array $weightsByKey): BasketIndexData
    {
        $closesByKey = array_filter(
            $closesByKey,
            fn (int|string $key): bool => ($weightsByKey[$key] ?? 0.0) > 0.0,
            ARRAY_FILTER_USE_KEY,
        );

        if ($closesByKey === []) {
            return BasketIndexData::empty();
        }

        $dates = $this->datesOf($closesByKey);
        $closesByKey = $this->carriedForward($closesByKey, $dates);
        $labels = [];
        $values = [];
        $level = self::BASE;
        $previous = null;

        foreach ($dates as $date) {
            if ($previous !== null) {
                $level *= 1 + $this->dailyReturn($closesByKey, $weightsByKey, $previous, $date);
            }

            $labels[] = $date;
            /** L'arrondi coupe la dérive du flottant : l'indice se lit, il ne se recompose pas. */
            $values[] = round($level, 6);
            $previous = $date;
        }

        return new BasketIndexData(labels: $labels, values: $values);
    }

    /**
     * @param  array<int|string, array<string, float>>  $closesByKey
     * @return list<string>
     */
    private function datesOf(array $closesByKey): array
    {
        $dates = [];

        foreach ($closesByKey as $closes) {
            $dates = array_merge($dates, array_keys($closes));
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * Chaque série comblée sur la grille commune, à partir de sa première cotation : une séance
     * manquée reprend le dernier cours connu. Les dates antérieures à l'entrée de l'instrument
     * restent vides — il n'a pas de cours à reporter, il n'a pas encore commencé à coter.
     *
     * @param  array<int|string, array<string, float>>  $closesByKey
     * @param  list<string>  $dates
     * @return array<int|string, array<string, float>>
     */
    private function carriedForward(array $closesByKey, array $dates): array
    {
        return array_map(function (array $closes) use ($dates): array {
            $filled = [];
            $last = null;

            foreach ($dates as $date) {
                $last = $closes[$date] ?? $last;

                if ($last !== null) {
                    $filled[$date] = $last;
                }
            }

            return $filled;
        }, $closesByKey);
    }

    /**
     * Le rendement du panier d'une séance à l'autre. Seuls comptent les instruments cotés aux deux
     * séances : les autres n'ont pas de rendement à apporter, et les poids se renormalisent sur
     * ceux qui restent pour que l'indice ne bouge pas du seul fait d'une entrée dans le panier.
     *
     * @param  array<int|string, array<string, float>>  $closesByKey
     * @param  array<int|string, float>  $weightsByKey
     */
    private function dailyReturn(array $closesByKey, array $weightsByKey, string $previous, string $date): float
    {
        $weighted = 0.0;
        $total = 0.0;

        foreach ($closesByKey as $key => $closes) {
            $before = $closes[$previous] ?? null;
            $after = $closes[$date] ?? null;

            if ($before === null || $after === null || $before <= 0.0) {
                continue;
            }

            $weight = $weightsByKey[$key];
            $weighted += $weight * ($after / $before - 1);
            $total += $weight;
        }

        return $total > 0.0 ? $weighted / $total : 0.0;
    }
}
