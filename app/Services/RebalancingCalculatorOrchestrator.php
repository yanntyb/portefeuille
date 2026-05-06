<?php

namespace App\Services;

use App\Contracts\Rebalancing;
use App\Domains\Security\Models\Security;
use App\Models\Transaction;
use App\Models\Wallet;

class RebalancingCalculatorOrchestrator
{
    public function __construct(
        private Rebalancing $calculator,
    ) {}

    /**
     * Validate allocation percentages sum to 100%.
     *
     * @throws \InvalidArgumentException
     */
    public function validateAllocations(array $allocations): void
    {
        if (empty($allocations)) {
            throw new \InvalidArgumentException('Au moins un titre requis');
        }

        $totalPercentage = array_sum(array_column($allocations, 'target_percentage'));
        if (abs($totalPercentage - 100) > 0.01) {
            throw new \InvalidArgumentException(
                "Le total des pourcentages doit être égal à 100%. Total actuel : {$totalPercentage}%"
            );
        }
    }

    /**
     * Prepare securities data from allocations, fetching quantities from transactions.
     * Includes N+1 optimization via bulk query.
     *
     * @return array<int, array{security_id: int, name: string, price: float, quantity: float, target_percentage: float}>
     */
    public function prepareSecuritiesData(array $allocations, ?Wallet $wallet): array
    {
        $securityIds = array_map(fn ($a) => (int) ($a['security_id'] ?? 0), $allocations);
        $securityIds = array_filter($securityIds);

        if (empty($securityIds)) {
            return [];
        }

        // Bulk load securities with prices
        $securities = Security::query()
            ->with('latestPrice')
            ->whereIn('id', $securityIds)
            ->get()
            ->keyBy('id');

        // Bulk load quantities
        $quantitiesQuery = Transaction::query()
            ->withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->whereIn('security_id', $securityIds);

        if ($wallet) {
            $quantitiesQuery->where('wallet_id', $wallet->id);
        }

        $quantities = $quantitiesQuery
            ->selectRaw('security_id, SUM(quantity) as total_qty')
            ->groupBy('security_id')
            ->pluck('total_qty', 'security_id');

        // Build result array maintaining allocation order
        $result = [];
        foreach ($allocations as $allocation) {
            $securityId = (int) ($allocation['security_id'] ?? 0);
            $security = $securities->get($securityId);

            if (! $security) {
                continue;
            }

            $result[] = [
                'security_id' => $securityId,
                'name' => $security->name,
                'price' => (float) ($security->latestPrice?->close ?? 0),
                'quantity' => (float) ($quantities->get($securityId) ?? 0),
                'target_percentage' => (float) ($allocation['target_percentage'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Calculate rebalancing with validation and data prep.
     */
    public function calculate(array $allocations, float $amount, ?Wallet $wallet): array
    {
        $this->validateAllocations($allocations);
        $securities = $this->prepareSecuritiesData($allocations, $wallet);

        return $this->calculator->calculate($securities, $amount);
    }
}
