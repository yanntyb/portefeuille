<?php

namespace App\Domains\Asset\Infrastructure\Adapters;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Asset;
use App\Domains\Asset\Ports\AssetPriceProviderPort;
use App\Infrastructure\Support\PythonScriptCaller;
use Illuminate\Support\Collection;

class YahooFinanceAdapter implements AssetPriceProviderPort
{
    public function getCurrentPrice(int $assetId): ?float
    {
        $asset = Asset::query()->find($assetId);

        if (! $asset || ! $asset->ticker) {
            return null;
        }

        try {
            $result = PythonScriptCaller::call('fetch_prices.py', [
                'ticker' => $asset->ticker,
                'start_date' => now()->subYear()->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]);

            if ($result['status'] !== 'ok' || empty($result['data'])) {
                return null;
            }

            $prices = $result['data'];

            return end($prices)['close'] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    public function getPriceHistory(int $assetId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $asset = Asset::query()->find($assetId);

        if (! $asset || ! $asset->ticker) {
            return collect();
        }

        $startDate ??= now()->subYear()->format('Y-m-d');
        $endDate ??= now()->format('Y-m-d');

        try {
            $result = PythonScriptCaller::call('fetch_prices.py', [
                'ticker' => $asset->ticker,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            if ($result['status'] !== 'ok') {
                return collect();
            }

            return collect($result['data'] ?? []);
        } catch (\Exception) {
            return collect();
        }
    }

    public function supports(AssetType $type): bool
    {
        return in_array($type, [AssetType::Stock, AssetType::ETF]);
    }
}
