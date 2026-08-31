<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\MarketSyncReportData;
use App\Contexts\Market\Datas\MarketSyncStateData;

/**
 * État de la synchronisation totale, et verrou qui interdit d'en lancer deux à la fois.
 *
 * Verrou et état sont le même port : la seule raison d'écrire l'un est de publier l'autre, et le
 * lecteur n'a jamais à les recoudre.
 */
interface MarketSyncStatePort
{
    public function current(): MarketSyncStateData;

    /**
     * Prend la place, si elle est libre. Atomique : deux clics simultanés n'obtiennent pas tous
     * les deux `true`.
     */
    public function begin(): bool;

    public function markRunning(): void;

    public function markSucceeded(MarketSyncReportData $report): void;

    public function markFailed(string $error): void;
}
