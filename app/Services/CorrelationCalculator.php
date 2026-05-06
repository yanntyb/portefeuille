<?php

namespace App\Services;

use App\Data\CorrelationResult;
use App\Enums\CorrelationPeriod;
use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Support\Collection;

class CorrelationCalculator
{
    private const int MIN_DATA_POINTS = 20;

    /**
     * @param  Collection<int, \App\Models\Security>  $securities  Securities with name loaded
     */
    public function compute(Collection $securities, CorrelationPeriod $period): ?CorrelationResult
    {
        if ($securities->count() < 2) {
            return null;
        }

        $logReturns = $this->loadLogReturns($securities, $period);

        if ($logReturns === null) {
            return null;
        }

        [$alignedReturns, $labels] = $logReturns;

        $n = count($labels);
        $matrix = $this->computeCorrelationMatrix($alignedReturns, $n);
        $average = $this->computeAverageCorrelation($matrix, $n);

        return new CorrelationResult(
            matrix: $matrix,
            labels: $labels,
            average: $average,
        );
    }

    /**
     * @return array{0: array<int, list<float>>, 1: list<string>}|null
     */
    private function loadLogReturns(Collection $securities, CorrelationPeriod $period): ?array
    {
        $securityIds = $securities->pluck('id')->all();

        $query = SecurityPrice::query()
            ->whereIn('security_id', $securityIds)
            ->orderBy('date');

        $startDate = $period->startDate();

        if ($startDate !== null) {
            $query->where('date', '>=', $startDate);
        }

        $prices = $query->get(['security_id', 'date', 'close']);

        $pricesBySecurityAndDate = [];

        foreach ($prices as $price) {
            $date = $price->date->format('Y-m-d');
            $pricesBySecurityAndDate[$price->security_id][$date] = (float) $price->close;
        }

        // Exclure les titres sans données de prix dans la période
        $securitiesWithPrices = $securities->filter(
            fn ($s) => isset($pricesBySecurityAndDate[$s->id])
        )->values();

        if ($securitiesWithPrices->count() < 2) {
            return null;
        }

        // Trouver les dates communes à tous les titres ayant des prix
        $dateSets = $securitiesWithPrices->map(
            fn ($s) => array_keys($pricesBySecurityAndDate[$s->id])
        )->all();

        $commonDates = array_shift($dateSets);

        foreach ($dateSets as $dates) {
            $commonDates = array_intersect($commonDates, $dates);
        }

        $commonDates = array_values($commonDates);
        sort($commonDates);

        if (count($commonDates) < self::MIN_DATA_POINTS + 1) {
            return null;
        }

        // Calculer les rendements log journaliers alignés
        $alignedReturns = [];
        $labels = [];

        foreach ($securitiesWithPrices as $security) {
            $returns = [];

            for ($i = 1; $i < count($commonDates); $i++) {
                $prevPrice = $pricesBySecurityAndDate[$security->id][$commonDates[$i - 1]];
                $currPrice = $pricesBySecurityAndDate[$security->id][$commonDates[$i]];

                if ($prevPrice <= 0) {
                    return null;
                }

                $returns[] = log($currPrice / $prevPrice);
            }

            $alignedReturns[] = $returns;
            $labels[] = $security->name;
        }

        return [$alignedReturns, $labels];
    }

    /**
     * @param  array<int, list<float>>  $alignedReturns
     * @return array<int, array<int, float>>
     */
    private function computeCorrelationMatrix(array $alignedReturns, int $n): array
    {
        $matrix = [];

        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];

            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 1.0;
                } elseif ($j < $i) {
                    $matrix[$i][$j] = $matrix[$j][$i];
                } else {
                    $matrix[$i][$j] = $this->pearsonCorrelation($alignedReturns[$i], $alignedReturns[$j]);
                }
            }
        }

        return $matrix;
    }

    /**
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function pearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $sumXY += $dx * $dy;
            $sumX2 += $dx * $dx;
            $sumY2 += $dy * $dy;
        }

        $denominator = sqrt($sumX2 * $sumY2);

        if ($denominator == 0.0) {
            return 0.0;
        }

        return $sumXY / $denominator;
    }

    /**
     * @param  array<int, array<int, float>>  $matrix
     */
    private function computeAverageCorrelation(array $matrix, int $n): float
    {
        $sum = 0.0;
        $count = 0;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $sum += $matrix[$i][$j];
                $count++;
            }
        }

        return $count > 0 ? round($sum / $count, 4) : 0.0;
    }
}
