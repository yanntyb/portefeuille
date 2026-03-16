<?php

namespace App\Services;

use App\Data\Simulation\MonteCarloResult;

class MonteCarloEngine
{
    public function compute(
        float $capitalInitial,
        float $versementMensuel,
        float $tauxMoyen,
        float $volatilite,
        int $duree,
        int $nbSimulations,
    ): MonteCarloResult {
        $paths = [];

        for ($i = 0; $i < $nbSimulations; $i++) {
            $portfolio = $capitalInitial;
            $paths[$i][0] = $portfolio;

            for ($t = 1; $t <= $duree; $t++) {
                for ($m = 0; $m < 12; $m++) {
                    $r = $this->sampleNormal($tauxMoyen / 12, $volatilite / sqrt(12));
                    $portfolio = ($portfolio + $versementMensuel) * (1 + $r);
                }

                $paths[$i][$t] = max(0.0, $portfolio);
            }
        }

        $p10 = $p50 = $p90 = $capitalInvesti = [];

        for ($t = 0; $t <= $duree; $t++) {
            $values = array_column($paths, $t);
            sort($values);
            $n = count($values);
            $p10[$t] = $values[(int) floor($n * 0.10)];
            $p50[$t] = $values[(int) floor($n * 0.50)];
            $p90[$t] = $values[(int) floor($n * 0.90)];
            $capitalInvesti[$t] = $capitalInitial + $versementMensuel * 12 * $t;
        }

        return new MonteCarloResult($duree, $p10, $p50, $p90, $capitalInvesti);
    }

    private function sampleNormal(float $mean, float $std): float
    {
        do {
            $u1 = mt_rand(1, PHP_INT_MAX) / PHP_INT_MAX;
        } while ($u1 === 0.0);

        $u2 = mt_rand(0, PHP_INT_MAX) / PHP_INT_MAX;
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        return $mean + $z * $std;
    }
}
