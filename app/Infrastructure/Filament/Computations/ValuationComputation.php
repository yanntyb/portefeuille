<?php

namespace App\Infrastructure\Filament\Computations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

class ValuationComputation
{
    /**
     * @return array{valuation: string, color: string}
     */
    public static function compute(Collection $securities): array
    {
        $valuation = $securities->sum(fn ($record) => $record->currentValuation());
        $invested = $securities->sum(fn ($record) => (float) ($record->total_invested ?? 0));

        return [
            'valuation' => Number::currency($valuation, 'EUR'),
            'color' => $valuation >= $invested ? 'success' : 'danger',
        ];
    }

    /**
     * @param  array{valuation: float, totalInvested: float}  $stats
     * @return array{valuation: string, color: string}
     */
    public static function computeFromStats(array $stats): array
    {
        return [
            'valuation' => Number::currency($stats['valuation'], 'EUR'),
            'color' => $stats['valuation'] >= $stats['totalInvested'] ? 'success' : 'danger',
        ];
    }
}
