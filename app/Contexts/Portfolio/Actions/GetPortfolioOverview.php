<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Portfolio\Models\Holding;
use Illuminate\Database\Eloquent\Builder;

class GetPortfolioOverview
{
    public function __construct(private PriceRepositoryContract $prices) {}

    /**
     * Sans `$types`, tout le portefeuille. Avec, une seule classe d'actif : les actions et la
     * crypto ont chacune leur page, et le partage se lit dans `InstrumentType::securities()`.
     *
     * @param  ?list<InstrumentType>  $types
     */
    public function __invoke(User $user, ?array $types = null): PortfolioOverviewData
    {
        $holdings = Holding::query()
            ->with('asset')
            ->where('user_id', $user->id)
            ->when($types !== null, fn (Builder $query) => $query->whereHas(
                'asset',
                fn (Builder $asset) => $asset->whereIn(
                    'type',
                    array_map(fn (InstrumentType $type): string => $type->value, $types),
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
