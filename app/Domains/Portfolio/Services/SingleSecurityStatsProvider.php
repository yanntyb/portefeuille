<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Portfolio\Contracts\TransactionRepositoryInterface;
use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Services\UserId;

class SingleSecurityStatsProvider
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private UserId $userId,
    ) {}

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
    public function computeStats(Security $record, ?int $walletId): array
    {
        $key = $record->id.':'.($walletId ?? 0);

        return $this->cache[$key] ??= $this->doCompute($record, $walletId);
    }

    /**
     * @return array<string, mixed>
     */
    private function doCompute(Security $record, ?int $walletId): array
    {
        $userId = $this->userId->get();

        $transactions = $this->transactionRepository
            ->forSecurity($record->id, $userId)
            ->filter(fn ($t) => !$walletId || $t->wallet_id === $walletId);

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
