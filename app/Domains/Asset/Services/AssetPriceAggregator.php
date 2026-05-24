<?php

namespace App\Domains\Asset\Services;

use App\Domains\AssetView\ValueObjects\PriceHistoryDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AssetPriceAggregator
{
    /** @param Collection<int, PriceHistoryDTO> $priceHistory */
    public function aggregateByWeek(Collection $priceHistory): Collection
    {
        if ($priceHistory->isEmpty()) {
            return collect();
        }

        return $priceHistory
            ->groupBy(function (PriceHistoryDTO $price): string {
                $date = Carbon::parse($price->date);
                $week = $date->weekOfYear;
                $year = $date->year;

                return sprintf('%d-W%02d', $year, $week);
            })
            ->map(function (Collection $weekGroup): array {
                $closes = $weekGroup->pluck('close')->toArray();
                $averageClose = array_sum($closes) / count($closes);

                $lastDate = $weekGroup->last();
                $lastDateParsed = Carbon::parse($lastDate->date);
                $weekStartDate = $lastDateParsed->copy()->startOfWeek();
                $weekEndDate = $lastDateParsed->copy()->endOfWeek();

                return [
                    'date' => $weekEndDate->format('Y-m-d'),
                    'close' => $averageClose,
                    'week' => sprintf('%d-W%02d', $lastDateParsed->year, $lastDateParsed->weekOfYear),
                ];
            })
            ->values();
    }
}
