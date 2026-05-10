<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Contracts\VolatilityCalculating;
use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Asset;
use Illuminate\Support\Collection;

class VolatilityCalculator implements VolatilityCalculating
{
    public function __construct(
        private AssetRepositoryInterface $assetRepository,
        private AssetPriceRepositoryInterface $priceRepository,
    ) {}

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

    public function forAsset(Asset $asset): ?float
    {
        $prices = AssetPrice::query()
            ->where('asset_id', $asset->id)
            ->orderBy('date')
            ->pluck('close')
            ->map(fn ($v) => (float) $v)
            ->values();

        return $this->annualizedVolatility($prices);
    }

    private function getPricesForSecurities(array $ids): Collection
    {
        return AssetPrice::query()
            ->whereIn('asset_id', $ids)
            ->orderBy('asset_id')
            ->orderBy('date')
            ->get(['asset_id', 'close'])
            ->groupBy('asset_id')
            ->map(fn ($group) => $group->pluck('close')->map(fn ($v) => (float) $v)->values());
    }

    public function forWallet(int $walletId, ?array $shownSecurityIds = null): float
    {
        $records = $this->assetRepository->forWallet($walletId);

        $totalValuation = (float) $records->sum(function (Asset $record) {
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

        $allPrices = $this->getPricesForSecurities($ids);

        $weightedVolatility = 0.0;

        foreach ($records as $record) {
            /** @var Asset $record */
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
