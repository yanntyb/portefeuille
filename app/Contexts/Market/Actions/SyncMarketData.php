<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Datas\MarketSyncReportData;

/**
 * Synchronisation totale : les trois sources, en série.
 *
 * En série et non en parallèle : chaque source ouvre un process Python, et le planificateur décale
 * déjà ses horaires pour ne jamais en croiser deux (voir `bootstrap/app.php`). Les cours d'abord,
 * puisque ce sont eux qui font bouger le grand chiffre du tableau de bord.
 */
class SyncMarketData
{
    public function __construct(
        private SyncAssetPrices $prices,
        private SyncAssetSectors $sectors,
        private SyncAssetDividends $dividends,
    ) {}

    public function __invoke(): MarketSyncReportData
    {
        return new MarketSyncReportData(
            prices: ($this->prices)(),
            sectors: ($this->sectors)(),
            dividends: ($this->dividends)(),
        );
    }
}
