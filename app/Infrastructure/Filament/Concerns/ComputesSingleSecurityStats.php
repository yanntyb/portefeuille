<?php

namespace App\Infrastructure\Filament\Concerns;

use App\Services\SingleSecurityStatsProvider;

/**
 * @property ?\Illuminate\Database\Eloquent\Model $record
 * @property ?int $walletId
 */
trait ComputesSingleSecurityStats
{
    /**
     * @return array{
     *     totalQuantity: float,
     *     pru: float,
     *     totalFees: float,
     *     totalInvested: float,
     *     totalRealizedGain: float,
     *     valuation: float,
     *     plusValue: float,
     *     plusValuePercentage: float,
     *     feesPercentage: float,
     *     close: ?float,
     *     priceDate: ?string,
     * }
     */
    protected function computeStats(): array
    {
        return app(SingleSecurityStatsProvider::class)
            ->computeStats($this->record, $this->walletId ?? null);
    }
}
