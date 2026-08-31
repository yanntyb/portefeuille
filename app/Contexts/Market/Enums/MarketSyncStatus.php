<?php

namespace App\Contexts\Market\Enums;

/**
 * État de la synchronisation totale déclenchée depuis l'interface.
 *
 * `Queued` et `Running` sont distincts : entre le clic et la prise en charge par le worker, il
 * n'y a rien à montrer d'autre que « en attente », et un worker éteint se lit alors dans l'état.
 */
enum MarketSyncStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** Une synchronisation tient la place : le bouton refuse d'en lancer une seconde. */
    public function isBusy(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
