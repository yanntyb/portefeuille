<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

class SingleSecurityStatsProvider
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

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
    public function computeStats(Model $record, ?int $walletId): array
    {
        $key = $record->id.':'.($walletId ?? 0);

        return $this->cache[$key] ??= $this->doCompute($record, $walletId);
    }

    /**
     * @return array<string, mixed>
     */
    private function doCompute(Model $record, ?int $walletId): array
    {
        $transactionsQuery = Transaction::query()
            ->where('security_id', $record->id);

        if ($walletId) {
            $transactionsQuery->where('wallet_id', $walletId);
        }

        $transactions = $transactionsQuery->get();

        $buyTransactions = $transactions->where('type', TransactionType::Buy);
        $sellTransactions = $transactions->where('type', TransactionType::Sell);

        $totalBuyQuantity = (float) $buyTransactions->sum('quantity');
        $totalSellQuantity = (float) $sellTransactions->sum('quantity');
        $totalQuantity = $totalBuyQuantity - $totalSellQuantity;

        $totalBuyCost = $buyTransactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);
        $pru = $totalBuyQuantity > 0 ? $totalBuyCost / $totalBuyQuantity : 0;

        $totalFees = (float) $transactions->sum('fees');
        $totalInvested = $totalQuantity * $pru + $totalFees;

        $totalRealizedGain = (float) $sellTransactions->sum('realized_gain');

        $record->loadMissing('latestPrice');
        $close = $record->latestPrice?->close;
        $valuation = ($close !== null) ? $totalQuantity * (float) $close : 0;

        $plusValue = $valuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        $priceDate = $record->latestPrice?->date?->translatedFormat('d M Y');

        return [
            'totalQuantity' => $totalQuantity,
            'pru' => $pru,
            'totalFees' => $totalFees,
            'totalInvested' => $totalInvested,
            'totalRealizedGain' => $totalRealizedGain,
            'valuation' => $valuation,
            'plusValue' => $plusValue,
            'plusValuePercentage' => $plusValuePercentage,
            'feesPercentage' => $feesPercentage,
            'close' => $close !== null ? (float) $close : null,
            'priceDate' => $priceDate,
        ];
    }
}
