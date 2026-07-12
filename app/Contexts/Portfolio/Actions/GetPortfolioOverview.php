<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Portfolio\Models\Holding;

class GetPortfolioOverview
{
    public function __construct(private PriceRepositoryContract $prices) {}

    public function __invoke(User $user): PortfolioOverviewData
    {
        $holdings = Holding::query()
            ->with('asset')
            ->where('user_id', $user->id)
            ->get();

        $lines = [];
        $totalValue = 0.0;
        $totalCost = 0.0;
        $totalGain = 0.0;
        /** @var array<string, float> $valueByType */
        $valueByType = [];

        foreach ($holdings as $holding) {
            $quantity = (float) $holding->quantity;
            $avgCost = $holding->avg_cost !== null ? (float) $holding->avg_cost : null;

            $price = $this->prices->latestForAsset($holding->asset_id);
            $lastPrice = $price !== null ? (float) $price->close : null;

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
                $key = $holding->asset->type->value;
                $valueByType[$key] = ($valueByType[$key] ?? 0.0) + $marketValue;
            }

            if ($gain !== null) {
                $totalCost += $cost;
                $totalGain += $gain;
            }
        }

        $totalGainPct = $totalCost > 0.0 ? $totalGain / $totalCost * 100 : 0.0;

        $allocation = [];
        foreach ($valueByType as $typeValue => $value) {
            $type = InstrumentType::from($typeValue);
            $allocation[] = new AllocationSliceData(
                label: $type->getLabel(),
                value: $value,
                pct: $totalValue > 0.0 ? $value / $totalValue * 100 : 0.0,
                color: $this->hexColorFor($type),
            );
        }

        return new PortfolioOverviewData(
            totalValue: $totalValue,
            totalCost: $totalCost,
            totalGain: $totalGain,
            totalGainPct: $totalGainPct,
            holdings: $lines,
            allocation: $allocation,
        );
    }

    private function hexColorFor(InstrumentType $type): string
    {
        return match ($type) {
            InstrumentType::Stock => '#4f46e5',
            InstrumentType::ETF => '#0ea5e9',
            InstrumentType::Crypto => '#f59e0b',
            InstrumentType::Bond => '#8b5cf6',
            InstrumentType::Commodity => '#eab308',
        };
    }
}
