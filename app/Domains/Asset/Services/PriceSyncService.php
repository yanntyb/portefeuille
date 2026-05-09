<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Infrastructure\Adapters\YahooFinanceAdapter;
use App\Domains\Asset\Models\Asset;
use App\Domains\Asset\ValueObjects\AssetPriceData;
use App\Domains\Asset\ValueObjects\PriceData;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

readonly class PriceSyncService
{
    private const DEFAULT_HISTORY_YEARS = 5;

    public function __construct(
        private AssetRepositoryInterface $assetRepository,
        private AssetPriceRepositoryInterface $priceRepository,
        private YahooFinanceAdapter $yahooAdapter,
        private PriceDataTransformer $transformer,
        private PricePersister $persister,
    ) {}

    /** @return array{synced: int, errors: int, error_details: array<int, string>} */
    public function syncAllStockAndEtfPrices(): array
    {
        $assets = collect([
            $this->assetRepository->findByType(AssetType::Stock),
            $this->assetRepository->findByType(AssetType::ETF),
        ])->flatten();

        return $this->syncAssets($assets);
    }

    /** @return array{synced: int, errors: int, error_details: array<int, string>} */
    public function syncAssetsOfType(AssetType $type): array
    {
        $assets = $this->assetRepository->findByType($type);

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

        $supportedAssets = $assets->filter(fn ($a) => $this->yahooAdapter->supports($a->type));

        foreach ($supportedAssets as $asset) {
            try {
                $this->syncAssetPrices($asset);
                $synced++;
                Log::info(sprintf('Synced prices for asset %d (%s)', $asset->id, $asset->name));
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[$asset->id] = sprintf('%s (ID: %d): %s', $asset->name, $asset->id, $e->getMessage());
                Log::warning(sprintf('Failed to sync asset %d: %s', $asset->id, $e->getMessage()));
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
        $latestDate = $this->priceRepository->findLatestForAsset($asset->id)?->date->toDateString();
        $startDate = $latestDate
            ? Carbon::parse($latestDate)->addDay()
            : now()->subYears(self::DEFAULT_HISTORY_YEARS);

        $priceHistory = $this->yahooAdapter->getPriceHistory(
            $asset->id,
            $startDate->format('Y-m-d'),
            now()->format('Y-m-d')
        );

        if ($priceHistory->isEmpty()) {
            return;
        }

        $priceDataCollection = $this->transformer->transform($priceHistory);
        $assetPrices = $priceDataCollection
            ->map(fn (PriceData $priceData) => AssetPriceData::fromPriceData($asset->id, $priceData)->toArray())
            ->values()
            ->toArray();

        $this->persister->persist($assetPrices);
    }
}
