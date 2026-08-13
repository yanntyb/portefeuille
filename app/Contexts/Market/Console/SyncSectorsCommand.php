<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncAssetSectors;
use Illuminate\Console\Command;

class SyncSectorsCommand extends Command
{
    protected $signature = 'market:sync-sectors {--asset= : Identifiant d\'un seul actif à synchroniser}';

    protected $description = 'Récupère la répartition sectorielle des actifs auprès du fournisseur de marché';

    public function handle(SyncAssetSectors $syncAssetSectors): int
    {
        $assetId = $this->option('asset');

        $synced = $syncAssetSectors($assetId !== null ? (int) $assetId : null);

        if ($synced === []) {
            $this->warn('Aucun secteur récupéré.');

            return self::SUCCESS;
        }

        foreach ($synced as $ticker => $count) {
            $this->line("{$ticker} : {$count} secteurs");
        }

        return self::SUCCESS;
    }
}
