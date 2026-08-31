<?php

namespace App\Contexts\Market\Jobs;

use App\Contexts\Market\Actions\SyncMarketData;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Coquille de file d'attente : elle publie l'état, l'action fait le travail.
 *
 * L'unicité ne passe pas par `ShouldBeUnique` mais par le verrou du port, pris avant le dispatch :
 * un job silencieusement avalé par le middleware d'unicité ne laisserait rien à afficher au
 * cliqueur, alors qu'un `begin()` refusé se lit tout de suite.
 */
class SyncMarketDataJob implements ShouldQueue
{
    use Queueable;

    /** Une seule tentative, comme le worker de développement (`queue:listen --tries=1`). */
    public int $tries = 1;

    /** Quinze minutes : trois lots Python d'affilée sur tout le catalogue. */
    public int $timeout = 900;

    public function handle(SyncMarketData $sync, MarketSyncStatePort $state): void
    {
        $state->markRunning();
        $state->markSucceeded($sync());
    }

    /**
     * Dernier mot du job raté : sans lui, le verrou tiendrait jusqu'à l'expiration de son TTL et le
     * bouton resterait à « en cours » une demi-heure.
     */
    public function failed(?Throwable $exception): void
    {
        app(MarketSyncStatePort::class)->markFailed(
            $exception?->getMessage() ?? 'Échec inconnu.',
        );
    }
}
