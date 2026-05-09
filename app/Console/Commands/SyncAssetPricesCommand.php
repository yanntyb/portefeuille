<?php

namespace App\Console\Commands;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Services\PriceSyncService;
use Illuminate\Console\Command;

class SyncAssetPricesCommand extends Command
{
    protected $signature = 'asset:sync-prices
                            {--type= : Sync specific asset type (stock, etf, crypto, bond, realestate, savings)}
                            {--all : Sync all supported asset types}';

    protected $description = 'Fetch and sync asset prices from Yahoo Finance';

    public function handle(PriceSyncService $priceSyncService): int
    {
        $type = $this->option('type') ?? 'stock';
        $all = $this->option('all');

        if ($all) {
            $result = $this->syncAll($priceSyncService);
        } else {
            $result = $this->syncType($priceSyncService, $type);
            if ($result === self::FAILURE) {
                return self::FAILURE;
            }
        }

        $this->displayResults($result);

        return self::SUCCESS;
    }

    private function syncAll(PriceSyncService $priceSyncService): array
    {
        $this->info('Syncing all asset prices...');

        return $priceSyncService->syncAllStockAndEtfPrices();
    }

    private function syncType(PriceSyncService $priceSyncService, string $type): int|array
    {
        $assetType = AssetType::tryFrom($type);

        if (! $assetType) {
            $this->error(sprintf('Invalid asset type: %s', $type));

            return self::FAILURE;
        }

        $this->info(sprintf('Syncing %s prices...', $type));

        return $priceSyncService->syncAssetsOfType($assetType);
    }

    /** @param array{synced: int, errors: int, error_details: array<int, string>} $result */
    private function displayResults(array $result): void
    {
        $this->newLine();
        $this->info(sprintf('✓ Synced: %d assets', $result['synced']));

        if ($result['errors'] > 0) {
            $this->warn(sprintf('✗ Errors: %d assets', $result['errors']));
            foreach ($result['error_details'] as $error) {
                $this->line(sprintf('  • %s', $error));
            }
        }

        $this->newLine();
    }
}
