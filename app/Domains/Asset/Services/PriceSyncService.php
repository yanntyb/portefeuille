<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Infrastructure\Adapters\YahooFinanceAdapter;
use App\Domains\Asset\Models\Asset;
use App\Domains\Asset\Models\AssetPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class PriceSyncService
{
    public function __construct(
        private AssetRepositoryInterface $assetRepository,
        private AssetPriceRepositoryInterface $priceRepository,
        private YahooFinanceAdapter $yahooAdapter,
    ) {}

    /** @return array{synced: int, errors: int, error_details: array<int, string>} */
    public function syncAllStockAndEtfPrices(): array
    {
        $assets = Asset::query()
            ->whereIn('type', [AssetType::Stock->value, AssetType::ETF->value])
            ->get();

        return $this->syncAssets($assets);
    }

    /** @return array{synced: int, errors: int, error_details: array<int, string>} */
    public function syncAssetsOfType(AssetType $type): array
    {
        $assets = Asset::query()
            ->where('type', $type->value)
            ->get();

        return $this->syncAssets($assets);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array{synced: int, errors: int, error_details: array<int, string>}
     */
    public function syncAssets(Collection $assets): array
    {
        $synced = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($assets as $asset) {
            if (! $this->yahooAdapter->supports($asset->type)) {
                continue;
            }

            try {
                $this->syncAssetPrices($asset);
                $synced++;
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[$asset->id] = sprintf(
                    '%s (ID: %d): %s',
                    $asset->name,
                    $asset->id,
                    $e->getMessage()
                );
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'error_details' => $errorDetails,
        ];
    }

    private function syncAssetPrices(Asset $asset): void
    {
        $latestDate = $this->priceRepository->getLatestDateForAssets([$asset->id])[$asset->id] ?? null;
        $startDate = $latestDate ? Carbon::parse($latestDate)->addDay() : now()->subYears(5);

        $priceHistory = $this->yahooAdapter->getPriceHistory(
            $asset->id,
            $startDate->format('Y-m-d'),
            now()->format('Y-m-d')
        );

        if ($priceHistory->isEmpty()) {
            return;
        }

        $prices = $priceHistory->map(fn (array $priceData) => [
            'asset_id' => $asset->id,
            'date' => $priceData['date'],
            'open' => $priceData['open'] ?? $priceData['close'],
            'high' => $priceData['high'] ?? $priceData['close'],
            'low' => $priceData['low'] ?? $priceData['close'],
            'close' => $priceData['close'],
            'volume' => $priceData['volume'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        foreach (array_chunk($prices, 100) as $chunk) {
            AssetPrice::insertOrIgnore($chunk);
        }
    }
}
