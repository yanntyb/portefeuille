<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Portfolio\Models\Holding;
use Illuminate\Database\Eloquent\Builder;

class GetPortfolioOverview
{
    public function __construct(private PriceRepositoryContract $prices) {}

    /**
     * Sans `$classes`, tout le portefeuille. Avec, une ou plusieurs expositions : chacune a sa
     * page et sa ligne au patrimoine, et le partage se lit dans `AssetClass`, nulle part ailleurs.
     *
     * @param  ?list<AssetClass>  $classes
     */
    public function __invoke(User $user, ?array $classes = null): PortfolioOverviewData
    {
        $holdings = Holding::query()
            ->with('asset')
            ->where('user_id', $user->id)
            ->when($classes !== null, fn (Builder $query) => $query->whereHas(
                'asset',
                fn (Builder $asset) => $asset->whereIn(
                    'asset_class',
                    array_map(fn (AssetClass $class): string => $class->value, $classes),
                ),
            ))
            ->get();

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        $lines = [];
        $totalValue = 0.0;
        $totalCost = 0.0;
        $totalGain = 0.0;

        foreach ($holdings as $holding) {
            $quantity = (float) $holding->quantity;
            $avgCost = $holding->avg_cost !== null ? (float) $holding->avg_cost : null;

            $lastPrice = $lastPrices[(int) $holding->asset_id] ?? null;

            $marketValue = $lastPrice !== null ? $quantity * $lastPrice : null;
            $cost = $avgCost !== null ? $quantity * $avgCost : null;
            $gain = ($marketValue !== null && $cost !== null) ? $marketValue - $cost : null;
            $gainPct = ($gain !== null && $cost !== null && $cost > 0.0) ? $gain / $cost * 100 : null;

            $lines[] = new HoldingLineData(
                assetId: (int) $holding->asset_id,
                assetName: $holding->asset->name,
                ticker: $holding->asset->ticker,
                type: $holding->asset->type,
                assetClass: $holding->asset->asset_class,
                quantity: $quantity,
                avgCost: $avgCost,
                lastPrice: $lastPrice,
                marketValue: $marketValue,
                gain: $gain,
                gainPct: $gainPct,
            );

            if ($marketValue !== null) {
                $totalValue += $marketValue;
            }

            if ($gain !== null) {
                $totalCost += $cost;
                $totalGain += $gain;
            }
        }

        $totalGainPct = $totalCost > 0.0 ? $totalGain / $totalCost * 100 : 0.0;

        return new PortfolioOverviewData(
            totalValue: $totalValue,
            totalCost: $totalCost,
            totalGain: $totalGain,
            totalGainPct: $totalGainPct,
            holdings: $lines,
        );
    }
}
