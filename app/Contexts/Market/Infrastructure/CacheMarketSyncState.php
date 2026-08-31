<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Datas\MarketSyncReportData;
use App\Contexts\Market\Datas\MarketSyncStateData;
use App\Contexts\Market\Enums\MarketSyncStatus;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use Illuminate\Support\Facades\Cache;

/**
 * État de synchronisation tenu en cache, sans table ni modèle : il n'y a qu'une dernière
 * exécution à raconter, et le store est `database` en production — l'état survit au redémarrage.
 *
 * Deux clés, deux durées de vie délibérément différentes :
 * - `market.sync.busy` est le verrou, posé par `Cache::add()` (atomique, donc à l'épreuve de deux
 *   clics simultanés) et retiré à la fin. Son TTL est le seul filet de récupération : le worker
 *   tourne avec `--tries=1`, un process tué ne rappellera jamais `failed()`, et sans expiration le
 *   bouton resterait bloqué à vie.
 * - `market.sync.state` est l'affichage, écrit `forever` : « dernière synchro le … » doit se lire
 *   longtemps après que le verrou a été rendu.
 */
class CacheMarketSyncState implements MarketSyncStatePort
{
    private const BUSY_KEY = 'market.sync.busy';

    private const STATE_KEY = 'market.sync.state';

    /** Trente minutes : bien au-delà d'une synchronisation complète, bien en deçà d'une journée. */
    private const BUSY_TTL = 1800;

    public function current(): MarketSyncStateData
    {
        $stored = Cache::get(self::STATE_KEY);

        if (! is_array($stored)) {
            return MarketSyncStateData::idle();
        }

        $state = MarketSyncStateData::fromArray($stored);

        /**
         * Verrou expiré sur un état encore occupé : le process est mort sans passer par `failed()`.
         * L'état le dit plutôt que de laisser tourner une icône pour toujours.
         */
        if ($state->status->isBusy() && ! Cache::has(self::BUSY_KEY)) {
            return new MarketSyncStateData(
                status: MarketSyncStatus::Failed,
                startedAt: $state->startedAt,
                finishedAt: now()->timestamp,
                error: 'Synchronisation interrompue.',
            );
        }

        return $state;
    }

    public function begin(): bool
    {
        if (! Cache::add(self::BUSY_KEY, 1, self::BUSY_TTL)) {
            return false;
        }

        $this->write(new MarketSyncStateData(
            status: MarketSyncStatus::Queued,
            startedAt: now()->timestamp,
        ));

        return true;
    }

    public function markRunning(): void
    {
        $this->write(new MarketSyncStateData(
            status: MarketSyncStatus::Running,
            startedAt: $this->current()->startedAt ?? now()->timestamp,
        ));
    }

    public function markSucceeded(MarketSyncReportData $report): void
    {
        $this->finish(new MarketSyncStateData(
            status: MarketSyncStatus::Succeeded,
            startedAt: $this->current()->startedAt,
            finishedAt: now()->timestamp,
            summary: $report->summary(),
        ));
    }

    public function markFailed(string $error): void
    {
        $this->finish(new MarketSyncStateData(
            status: MarketSyncStatus::Failed,
            startedAt: $this->current()->startedAt,
            finishedAt: now()->timestamp,
            error: $error,
        ));
    }

    /** L'état est publié avant que le verrou ne tombe : jamais de fenêtre « libre et en cours ». */
    private function finish(MarketSyncStateData $state): void
    {
        $this->write($state);

        Cache::forget(self::BUSY_KEY);
    }

    private function write(MarketSyncStateData $state): void
    {
        Cache::forever(self::STATE_KEY, $state->toArray());
    }
}
