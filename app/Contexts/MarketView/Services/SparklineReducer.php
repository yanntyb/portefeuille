<?php

namespace App\Contexts\MarketView\Services;

/**
 * Ce qu'une sparkline demande d'une série de cours : sa variation d'un bout à l'autre, et assez de
 * points pour être lisible sans gonfler la charge utile.
 */
class SparklineReducer
{
    /** @param  list<float>  $close */
    public function changePct(array $close): ?float
    {
        $count = count($close);

        if ($count < 2 || $close[0] === 0.0) {
            return null;
        }

        return ($close[$count - 1] - $close[0]) / $close[0] * 100;
    }

    /**
     * @param  list<float>  $close
     * @return list<float>
     */
    public function downsample(array $close, int $maxPoints): array
    {
        $count = count($close);

        if ($count <= $maxPoints) {
            return $close;
        }

        $points = [];

        for ($step = 0; $step < $maxPoints; $step++) {
            $points[] = $close[(int) round($step * ($count - 1) / ($maxPoints - 1))];
        }

        return $points;
    }
}
