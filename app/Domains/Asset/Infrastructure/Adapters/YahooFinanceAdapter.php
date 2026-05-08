<?php

namespace App\Domains\Asset\Infrastructure\Adapters;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Ports\AssetPriceProviderPort;
use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Support\Collection;

class YahooFinanceAdapter implements AssetPriceProviderPort
{
    public function getCurrentPrice(int $assetId): ?float
    {
        return SecurityPrice::query()
            ->where('security_id', $assetId)
            ->orderByDesc('date')
            ->value('close');
    }

    public function getPriceHistory(int $assetId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = SecurityPrice::query()
            ->where('security_id', $assetId)
            ->orderBy('date');

        if ($startDate !== null) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('date', '<=', $endDate);
        }

        return $query->get(['date', 'open', 'high', 'low', 'close', 'volume'])
            ->map(fn ($price) => [
                'date' => $price->date,
                'open' => (float) $price->open,
                'high' => (float) $price->high,
                'low' => (float) $price->low,
                'close' => (float) $price->close,
                'volume' => (int) $price->volume,
            ]);
    }

    public function supports(AssetType $type): bool
    {
        return in_array($type, [AssetType::Stock, AssetType::ETF]);
    }
}
