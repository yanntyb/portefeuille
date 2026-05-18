<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Contracts\Rebalancing;
use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Portfolio\Models\HoldingsProjection;
use App\Domains\Portfolio\Models\Wallet;

class RebalancingCalculatorOrchestrator
{
    public function __construct(
        private Rebalancing $calculator,
        private AssetPriceRepositoryInterface $priceRepository,
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
        $securityIds = array_map(fn ($a) => (int) ($a['asset_id'] ?? 0), $allocations);
        $securityIds = array_filter($securityIds);

        if (empty($securityIds)) {
            return [];
        }

        // Bulk load securities
        $securities = Asset::query()
            ->whereIn('id', $securityIds)
            ->get()
            ->keyBy('id');

        // Bulk load latest prices
        $priceMap = collect($securityIds)
            ->mapWithKeys(fn ($id) => [$id => $this->priceRepository->findLatestForAsset($id)])
            ->all();

        // Bulk load quantities from HoldingsProjection read model
        $quantitiesQuery = HoldingsProjection::query()
            ->withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->whereIn('asset_id', $securityIds);

        if ($wallet) {
            $quantities = $quantitiesQuery
                ->where('wallet_id', $wallet->id)
                ->pluck('quantity', 'asset_id');
        } else {
            // For global view, sum quantities across all wallets per asset
            $quantities = $quantitiesQuery
                ->selectRaw('asset_id, SUM(quantity) as total_qty')
                ->groupBy('asset_id')
                ->pluck('total_qty', 'asset_id');
        }

        // Build result array maintaining allocation order
        $result = [];
        foreach ($allocations as $allocation) {
            $securityId = (int) ($allocation['asset_id'] ?? 0);
            $security = $securities->get($securityId);

            if (! $security) {
                continue;
            }

            $result[] = [
                'asset_id' => $securityId,
                'name' => $security->name,
                'price' => (float) ($priceMap[$securityId]?->close ?? 0),
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
