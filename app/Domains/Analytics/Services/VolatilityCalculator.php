<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Contracts\VolatilityCalculating;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Support\Collection;

class VolatilityCalculator implements VolatilityCalculating
{
    /**
     * @param  Collection<int, float>  $prices
     */
    public function annualizedVolatility(Collection $prices): ?float
    {
        if ($prices->count() < 30) {
            return null;
        }

        $returns = [];

        for ($i = 1; $i < $prices->count(); $i++) {
            $prev = $prices[$i - 1];

            if ($prev == 0.0) {
                continue;
            }

            $returns[] = ($prices[$i] - $prev) / $prev;
        }

        $n = count($returns);

        if ($n < 2) {
            return null;
        }

        $mean = array_sum($returns) / $n;
        $variance = array_sum(array_map(fn (float $r) => ($r - $mean) ** 2, $returns)) / ($n - 1);

        return sqrt($variance) * sqrt(252) * 100;
    }

    public function forSecurity(Security $security): ?float
    {
        $prices = SecurityPrice::query()
            ->where('security_id', $security->id)
            ->orderBy('date')
            ->pluck('close')
            ->map(fn ($v) => (float) $v)
            ->values();

        return $this->annualizedVolatility($prices);
    }

    public function forWallet(Wallet $wallet, ?array $shownSecurityIds = null): float
    {
        $records = Security::query()
            ->forWallet($wallet)
            ->with('latestPrice')
            ->get();

        $totalValuation = (float) $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        if ($totalValuation <= 0) {
            return 15.0;
        }

        if ($shownSecurityIds !== null) {
            $records = $records->whereIn('id', $shownSecurityIds);
        }

        $ids = $records->pluck('id')->all();

        $allPrices = SecurityPrice::query()
            ->whereIn('security_id', $ids)
            ->orderBy('security_id')
            ->orderBy('date')
            ->get(['security_id', 'close'])
            ->groupBy('security_id')
            ->map(fn ($group) => $group->pluck('close')->map(fn ($v) => (float) $v)->values());

        $weightedVolatility = 0.0;

        foreach ($records as $record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null || (float) $record->total_quantity <= 0) {
                continue;
            }

            $weight = ((float) $record->total_quantity * (float) $close) / $totalValuation;
            $prices = $allPrices->get($record->id, collect());
            $sigma = $this->annualizedVolatility($prices);

            if ($sigma === null) {
                continue;
            }

            $weightedVolatility += $weight * $sigma;
        }

        return $weightedVolatility > 0
            ? round($weightedVolatility, 2)
            : 15.0;
    }
}
