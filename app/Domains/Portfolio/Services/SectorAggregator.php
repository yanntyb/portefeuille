<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Security\Enums\Sector;
use App\Infrastructure\Support\ChartColors;
use Illuminate\Support\Collection;

class SectorAggregator
{
    /**
     * @param  Collection<int, \App\Models\Security>  $securities  With latestPrice and sectors loaded
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    public function buildStackedSectorData(Collection $securities): array
    {
        /** @var array<string, array<int, float>> */
        $sectorBySecurity = [];
        $sectorTotals = [];
        $securityNames = [];

        foreach ($securities as $security) {
            $quantity = (float) $security->total_quantity;
            $price = $security->latestPrice?->close;

            if ($quantity <= 0 || $price === null) {
                continue;
            }

            $valuation = $quantity * (float) $price;
            $securityNames[$security->id] = $security->name;

            foreach ($security->sectors as $sectorRecord) {
                $key = $sectorRecord->sector->value;
                $amount = $valuation * (float) $sectorRecord->weight;
                $sectorBySecurity[$key][$security->id] = ($sectorBySecurity[$key][$security->id] ?? 0) + $amount;
                $sectorTotals[$key] = ($sectorTotals[$key] ?? 0) + $amount;
            }
        }

        if ($sectorTotals === []) {
            return ['datasets' => [], 'labels' => []];
        }

        $grandTotal = array_sum($sectorTotals);

        arsort($sectorTotals);
        $sortedSectorKeys = array_keys($sectorTotals);

        $labels = array_map(fn ($key) => Sector::from($key)->getLabel(), $sortedSectorKeys);

        $datasets = [];
        $colorIndex = 0;

        foreach (array_keys($securityNames) as $securityId) {
            $data = [];
            foreach ($sortedSectorKeys as $sectorKey) {
                $amount = $sectorBySecurity[$sectorKey][$securityId] ?? 0;
                $data[] = $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 1) : 0;
            }

            $datasets[] = [
                'label' => $securityNames[$securityId],
                'data' => $data,
                'backgroundColor' => ChartColors::at($colorIndex),
                'borderWidth' => 0,
            ];
            $colorIndex++;
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
}
