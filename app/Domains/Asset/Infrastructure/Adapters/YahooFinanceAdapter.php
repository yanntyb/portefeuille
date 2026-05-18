<?php

namespace App\Domains\Asset\Infrastructure\Adapters;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Ports\AssetPriceProviderPort;
use App\Domains\Asset\Ports\AssetProviderPort;
use App\Domains\Asset\ValueObjects\AssetData;
use App\Infrastructure\Support\PythonScriptCaller;
use Illuminate\Support\Collection;

class YahooFinanceAdapter implements AssetPriceProviderPort, AssetProviderPort
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

    public function findBySymbol(string $symbol, AssetType $type): ?AssetData
    {
        try {
            $search = PythonScriptCaller::call('search_ticker.py', ['query' => $symbol]);

            if ($search['status'] !== 'ok' || empty($search['data'])) {
                return null;
            }

            $hit = $search['data'][0];

            return new AssetData(
                symbol: $hit['symbol'],
                name: $hit['name'],
                type: $type,
                exchange: $hit['exchange'] ?? null,
                sectors: $this->resolveSectors($symbol),
            );
        } catch (\Exception) {
            return null;
        }
    }

    /** @return array<int, Sector> */
    private function resolveSectors(string $symbol): array
    {
        try {
            $result = PythonScriptCaller::call('fetch_sectors.py', ['ticker' => $symbol]);

            if ($result['status'] !== 'ok' || empty($result['data'])) {
                return [];
            }

            return array_values(array_filter(
                array_map(fn (string $key) => Sector::tryFrom($key), array_keys($result['data']))
            ));
        } catch (\Exception) {
            return [];
        }
    }
}
