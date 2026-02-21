<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\AccountType;
use App\Models\Security;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class PortfolioStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $accountTypes = [AccountType::Pea, AccountType::Cto];

        $totalValuation = 0;
        $totalInvested = 0;

        /** @var array<string, float> */
        $valuationByAccount = [];

        foreach ($accountTypes as $accountType) {
            $securities = Security::query()
                ->forAccountType($accountType)
                ->with('latestPrice')
                ->get();

            $accountValuation = $securities->sum(function ($security) {
                $close = $security->latestPrice?->close;

                if ($close === null || $security->total_quantity === null) {
                    return 0;
                }

                return (float) $security->total_quantity * (float) $close;
            });

            $accountInvested = $securities->sum(fn ($security) => (float) ($security->total_invested ?? 0));

            $valuationByAccount[$accountType->getLabel()] = $accountValuation;
            $totalValuation += $accountValuation;
            $totalInvested += $accountInvested;
        }

        $plusValue = $totalValuation - $totalInvested;
        $percentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;

        $plusValueLabel = Number::currency($plusValue, 'EUR').' ('.Number::format($percentage, 2).' %)';

        $repartitionDescription = collect($valuationByAccount)
            ->map(fn (float $value, string $label) => $label.' : '.Number::currency($value, 'EUR'))
            ->implode(' | ');

        return [
            Stat::make('Valorisation totale', Number::currency($totalValuation, 'EUR'))
                ->description($repartitionDescription),
            Stat::make('Total investi', Number::currency($totalInvested, 'EUR')),
            Stat::make('Plus-value globale', $plusValueLabel)
                ->color($plusValue >= 0 ? 'success' : 'danger'),
        ];
    }
}
