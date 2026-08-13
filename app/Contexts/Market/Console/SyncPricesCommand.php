<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncAssetPrices;
use Illuminate\Console\Command;

class SyncPricesCommand extends Command
{
    protected $signature = 'market:sync-prices
                            {--asset= : Identifiant d\'un seul actif à synchroniser}
                            {--since= : Date de début forcée (Y-m-d), sinon reprise au dernier prix connu}';

    protected $description = 'Récupère les prix quotidiens des instruments auprès du fournisseur de marché';

    public function handle(SyncAssetPrices $syncAssetPrices): int
    {
        $assetId = $this->option('asset');

        $report = $syncAssetPrices(
            $assetId !== null ? (int) $assetId : null,
            $this->option('since'),
        );

        if ($report->total() === 0) {
            $this->warn('Aucun instrument à synchroniser.');

            return self::SUCCESS;
        }

        foreach ($report->synced as $ticker => $count) {
            $this->line("{$ticker} : {$count} prix");
        }

        foreach ($report->failed as $ticker) {
            $this->line("{$ticker} : échec");
        }

        $this->newLine();
        $this->line($this->summary($report->total(), $report->syncedCount(), count($report->failed)));

        return $report->isTotalFailure() ? self::FAILURE : self::SUCCESS;
    }

    private function summary(int $total, int $synced, int $failed): string
    {
        return sprintf(
            '%d instrument%s, %d synchronisé%s, %d échec%s',
            $total, $this->plural($total),
            $synced, $this->plural($synced),
            $failed, $this->plural($failed),
        );
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
