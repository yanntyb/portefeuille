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
        $type = $this->option('type');
        $all = $this->option('all');

        if (! $type && ! $all) {
            $type = 'stock';
        }

        try {
            if ($all) {
                $this->info('Syncing all asset prices...');
                $result = $priceSyncService->syncAllStockAndEtfPrices();
            } else {
                $assetType = AssetType::tryFrom($type);
                if (! $assetType) {
                    $this->error(sprintf('Invalid asset type: %s', $type));

                    return self::FAILURE;
                }

                $this->info(sprintf('Syncing %s prices...', $type));
                $result = $priceSyncService->syncAssetsOfType($assetType);
            }

            $this->displayResults($result);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error(sprintf('Sync failed: %s', $e->getMessage()));

            return self::FAILURE;
        }
    }

    /** @param array{synced: int, errors: int, error_details: array<int, string>} $result */
    private function displayResults(array $result): void
    {
        $this->newLine();
        $this->info(sprintf('✓ Synced: %d assets', $result['synced']));

        if ($result['errors'] > 0) {
            $this->warn(sprintf('✗ Errors: %d assets', $result['errors']));
            foreach ($result['error_details'] as $assetId => $error) {
                $this->line(sprintf('  • %s', $error));
            }
        }

        $this->newLine();
    }
}
