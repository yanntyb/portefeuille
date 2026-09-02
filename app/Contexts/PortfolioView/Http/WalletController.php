<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\PortfolioView\Pages\WalletPage;
use Inertia\Response;

/**
 * La page d'une enveloppe de détention, `/enveloppes/{id}`. Une adresse par ligne de `wallets`,
 * pas par cas de `AccountType` : trois PEA ont trois anciennetés. 404 et non 403 sur l'enveloppe
 * d'un autre : le code ne doit pas dire qu'elle existe.
 *
 * L'en-tête est synchrone pour qu'il ne saute pas à l'arrivée ; chaque autre section attend son
 * propre groupe différé, pour ne pas retarder le premier rendu.
 *
 * L'instantané hors-ligne ne porte pas cette page : ses sections différées afficheront leur état
 * « indisponible hors-ligne », c'est assumé.
 */
class WalletController
{
    public function __construct(private WalletPage $page) {}

    public function __invoke(int $id): Response
    {
        $page = $this->page->for(auth()->id() ?? 0, $id) ?? abort(404);

        return $page->render('Wallet/Show');
    }
}
