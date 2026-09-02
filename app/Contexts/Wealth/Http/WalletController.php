<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Wealth\Actions\GetWalletAccount;
use App\Contexts\Wealth\Actions\GetWalletBreakdown;
use App\Contexts\Wealth\Actions\GetWalletPositions;
use App\Contexts\Wealth\Actions\GetWalletSeries;
use App\Contexts\Wealth\Actions\GetWalletTransactions;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page d'une enveloppe de détention. Calquée sur `MarketView\Http\AssetClassController` :
 * l'en-tête synchrone pour qu'il ne saute pas à l'arrivée, chaque autre section derrière son propre
 * groupe différé, pour ne pas retarder le premier rendu de la page.
 *
 * L'instantané hors-ligne ne porte pas cette page : ses sections différées afficheront leur état
 * « indisponible hors-ligne », c'est assumé.
 */
class WalletController
{
    public function __construct(
        private GetWalletAccount $getAccount,
        private GetWalletPositions $getPositions,
        private GetWalletBreakdown $getBreakdown,
        private GetWalletSeries $getSeries,
        private GetWalletTransactions $getTransactions,
    ) {}

    public function __invoke(int $id): Response
    {
        $userId = auth()->id() ?? 0;

        $account = ($this->getAccount)($userId, $id);

        /**
         * 404 et non page vide : l'enveloppe inconnue et celle d'un autre porteur se confondent
         * ici, et le code ne doit pas révéler laquelle des deux a été demandée.
         */
        if ($account === null) {
            abort(404);
        }

        return Inertia::render('Wallet/Show', [
            'account' => $account,
            'positions' => Inertia::defer(fn (): array => ($this->getPositions)($userId, $id), 'positions'),
            'breakdown' => Inertia::defer(fn (): array => ($this->getBreakdown)($userId, $id), 'repartition'),
            'evolution' => Inertia::defer(fn (): ClassSeriesData => ($this->getSeries)($userId, $id), 'evolution'),
            /**
             * Différée comme les groupes voisins : Inertia résout tous les groupes différés dès
             * le remplacement du composant, repliée ou non — seule la section attend le dépli
             * pour afficher ce qui est déjà arrivé. La déférer sert seulement à ne pas retarder
             * le premier rendu de la page derrière le journal complet du compte.
             */
            'transactions' => Inertia::defer(fn (): array => ($this->getTransactions)($userId, $id), 'transactions'),
        ]);
    }
}
