<?php

namespace App\Contexts\Market\Services;

use App\Contexts\Market\Datas\CorrelationMatrixData;

/**
 * La corrélation de Pearson entre les rendements journaliers de plusieurs instruments, paire par
 * paire. Ce sont les rendements qui se corrèlent, jamais les cours : deux cours qui montent chacun
 * de leur côté paraissent liés par leur seule tendance, alors que leurs variations quotidiennes
 * peuvent n'avoir aucun rapport.
 *
 * Chaque paire s'aligne sur ses seules séances communes : un instrument coté depuis six mois se
 * compare quand même à un plus ancien, sur la fenêtre qu'ils partagent.
 */
class Correlation
{
    /** En deçà, la corrélation d'une paire tient plus du hasard que de la mesure. */
    public const MIN_RETURNS = 30;

    /**
     * @param  array<int|string, array<string, float>>  $closesByKey  Clôtures par date, par instrument.
     */
    public function matrix(array $closesByKey): CorrelationMatrixData
    {
        $keys = array_keys($closesByKey);
        $rows = [];

        foreach ($keys as $line => $key) {
            $row = [];

            foreach ($keys as $column => $other) {
                $row[] = $line === $column
                    ? 1.0
                    : $this->pairOf($closesByKey[$key], $closesByKey[$other]);
            }

            $rows[] = $row;
        }

        return new CorrelationMatrixData(keys: $keys, rows: $rows);
    }

    /**
     * @param  array<string, float>  $first
     * @param  array<string, float>  $second
     */
    private function pairOf(array $first, array $second): ?float
    {
        $dates = array_keys(array_intersect_key($first, $second));
        sort($dates);

        $firstReturns = $this->returnsOn($dates, $first);
        $secondReturns = $this->returnsOn($dates, $second);

        if (count($firstReturns) < self::MIN_RETURNS) {
            return null;
        }

        return $this->pearson($firstReturns, $secondReturns);
    }

    /**
     * Les rendements d'une série sur les seules dates retenues, dans leur ordre. Une clôture nulle
     * ou négative ne donne pas de rendement : la séance suivante repart de la dernière clôture
     * exploitable plutôt que de diviser par zéro.
     *
     * @param  list<string>  $dates
     * @param  array<string, float>  $closes
     * @return list<float>
     */
    private function returnsOn(array $dates, array $closes): array
    {
        $returns = [];
        $previous = null;

        foreach ($dates as $date) {
            $close = $closes[$date];

            if ($previous !== null && $previous > 0.0) {
                $returns[] = $close / $previous - 1;
            }

            $previous = $close;
        }

        return $returns;
    }

    /**
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    private function pearson(array $first, array $second): ?float
    {
        $count = count($first);
        $firstMean = array_sum($first) / $count;
        $secondMean = array_sum($second) / $count;

        $covariance = 0.0;
        $firstVariance = 0.0;
        $secondVariance = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $firstGap = $first[$index] - $firstMean;
            $secondGap = $second[$index] - $secondMean;

            $covariance += $firstGap * $secondGap;
            $firstVariance += $firstGap ** 2;
            $secondVariance += $secondGap ** 2;
        }

        /** Une série plate n'a pas de dispersion : rien à corréler, pas de division possible. */
        if ($firstVariance <= 0.0 || $secondVariance <= 0.0) {
            return null;
        }

        return $covariance / sqrt($firstVariance * $secondVariance);
    }
}
