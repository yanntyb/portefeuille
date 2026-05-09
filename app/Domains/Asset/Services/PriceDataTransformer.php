<?php

namespace App\Domains\Asset\Services;

use Illuminate\Support\Collection;

readonly class PriceDataTransformer
{
    /**
     * Transform raw price data from adapter to AssetPrice attributes.
     *
     * @param  Collection<int, array{date: string, close: float, open?: float, high?: float, low?: float, volume?: int}>  $priceHistory
     * @return array<int, array{asset_id: int, date: string, open: string, high: string, low: string, close: string, volume: int, created_at: string, updated_at: string}>
     */
    public function transform(int $assetId, Collection $priceHistory): array
    {
        return $priceHistory
            ->map(fn (array $priceData) => $this->transformPrice($assetId, $priceData))
            ->toArray();
    }

    /** @return array{asset_id: int, date: string, open: string, high: string, low: string, close: string, volume: int, created_at: string, updated_at: string} */
    private function transformPrice(int $assetId, array $priceData): array
    {
        $close = $priceData['close'];

        return [
            'asset_id' => $assetId,
            'date' => $priceData['date'],
            'open' => (string) ($priceData['open'] ?? $close),
            'high' => (string) ($priceData['high'] ?? $close),
            'low' => (string) ($priceData['low'] ?? $close),
            'close' => (string) $close,
            'volume' => (int) ($priceData['volume'] ?? 0),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
