<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Jobs\SyncMarketDataJob;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use Illuminate\Http\Response;

/**
 * Mise en file de la synchronisation totale.
 *
 * Réponse vide et non redirection : l'appel vient d'une requête XHR du bouton, pas d'une visite
 * Inertia — recharger la page ferait repartir les quatre groupes différés du tableau de bord pour
 * rien. C'est le sondage de la prop `sync` qui raconte la suite.
 *
 * Idempotent : un second appel pendant qu'une synchronisation tourne ne met rien en file et rend le
 * même 204. Ce n'est pas une erreur du cliqueur, il n'a rien à corriger.
 */
class StartSyncController
{
    public function __invoke(MarketSyncStatePort $state): Response
    {
        if ($state->begin()) {
            SyncMarketDataJob::dispatch();
        }

        return response()->noContent();
    }
}
