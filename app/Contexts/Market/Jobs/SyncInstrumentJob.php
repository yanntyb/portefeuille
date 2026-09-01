<?php

namespace App\Contexts\Market\Jobs;

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Actions\SyncAssetSectors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * L'historique d'un instrument fraîchement créé : cinq ans, la profondeur d'`InstrumentCatalogSeeder`.
 *
 * Le job ne filtre rien lui-même — chacune des trois actions teste déjà le `supportsX()` de son
 * port avant d'ouvrir un process Python. Une crypto n'a pas de ventilation sectorielle, et c'est
 * `SyncAssetSectors` qui le sait.
 *
 * Pas de port d'état : `MarketSyncStatePort` raconte la synchronisation totale, celle du bouton du
 * tableau de bord. Y publier l'arrivée d'un seul instrument ferait clignoter le bouton pour un
 * travail qui ne le concerne pas.
 */
class SyncInstrumentJob implements ShouldQueue
{
    use Queueable;

    /** Une seule tentative, comme `SyncMarketDataJob` et le worker de développement. */
    public int $tries = 1;

    /** Trois passes Python sur un seul instrument : loin des quinze minutes du lot complet. */
    public int $timeout = 300;

    private const YEARS_OF_HISTORY = 5;

    public function __construct(public int $instrumentId) {}

    public function handle(
        SyncAssetPrices $prices,
        SyncAssetSectors $sectors,
        SyncAssetDividends $dividends,
    ): void {
        $since = today()->subYears(self::YEARS_OF_HISTORY)->format('Y-m-d');

        ($prices)($this->instrumentId, $since);
        ($sectors)($this->instrumentId);
        ($dividends)($this->instrumentId, $since);
    }
}
