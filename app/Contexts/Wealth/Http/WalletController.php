<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Wealth\Actions\GetWalletAccount;
use App\Contexts\Wealth\Actions\GetWalletBreakdown;
use App\Contexts\Wealth\Actions\GetWalletPositions;
use App\Contexts\Wealth\Actions\GetWalletSeries;
use App\Contexts\Wealth\Actions\GetWalletTransactions;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page d'une enveloppe de détention. Calquée sur `MarketView\Http\AssetClassController` :
 * l'en-tête synchrone pour qu'il ne saute pas à l'arrivée, chaque autre section derrière son propre
 * groupe différé, qui ne part qu'au dépli pour les sections repliées.
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
            'evolution' => Inertia::defer(fn () => ($this->getSeries)($userId, $id), 'evolution'),
            /** Repliée à l'arrivée : l'historique du compte ne se charge que pour qui le déplie. */
            'transactions' => Inertia::defer(fn (): array => ($this->getTransactions)($userId, $id), 'transactions'),
        ]);
    }
}
