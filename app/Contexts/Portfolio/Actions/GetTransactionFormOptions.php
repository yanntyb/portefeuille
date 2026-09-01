<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Datas\HeldStockData;
use App\Contexts\Portfolio\Datas\InstrumentOptionData;
use App\Contexts\Portfolio\Datas\TransactionFormOptionsData;
use App\Contexts\Portfolio\Datas\WalletOptionData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\CashLedger;

class GetTransactionFormOptions
{
    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private PriceRepositoryContract $prices,
        private GetCashMovements $cashMovements,
        private CashLedger $ledger,
    ) {}

    public function __invoke(int $userId): TransactionFormOptionsData
    {
        $instruments = $this->instruments->findAll();

        /**
         * Les mouvements une seule fois, le solde de chaque enveloppe ensuite : les relire par
         * enveloppe rouvrirait un N+1 sur tout l'historique.
         */
        $movements = ($this->cashMovements)($userId);
        $today = now()->format('Y-m-d');

        /**
         * Les derniers cours en une requête : un instrument chargeant le sien rouvrirait un N+1 sur
         * tout le catalogue. Même parade que `GetPortfolioOverview`.
         */
        $closes = $this->prices->latestClosesForAssets(
            $instruments->pluck('id')->all(),
        );

        return new TransactionFormOptionsData(
            wallets: Wallet::query()
                ->where('user_id', $userId)
                ->orderBy('name')
                ->get()
                ->map(fn (Wallet $wallet): WalletOptionData => new WalletOptionData(
                    id: $wallet->id,
                    name: $wallet->name,
                    broker: $wallet->broker,
                    accountType: $wallet->account_type->value,
                    accountTypeLabel: $wallet->account_type->getLabel(),
                    /**
                     * Le plafond d'un retrait pendant la frappe, au solde du jour. Le formulaire
                     * s'en sert comme il se sert de `held` pour plafonner une vente ; c'est
                     * `TransactionRequest` qui juge, en recomptant à la date saisie.
                     */
                    cashBalance: $this->ledger->balanceAt($movements, $wallet->id, $today),
                ))
                ->values()
                ->all(),
            /**
             * Tout le catalogue et pas seulement les positions tenues : la saisie sert justement à
             * entrer un premier achat. Le scope global `market` du modèle écarte déjà les lignes
             * hors marché.
             */
            instruments: $instruments
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (Instrument $instrument): InstrumentOptionData => new InstrumentOptionData(
                    id: $instrument->id,
                    name: $instrument->name,
                    ticker: $instrument->ticker,
                    assetClass: $instrument->asset_class->value,
                    assetClassLabel: $instrument->asset_class->getLabel(),
                    lastPrice: $closes[$instrument->id] ?? null,
                ))
                ->values()
                ->all(),
            /**
             * Les positions telles que la projection les tient : le formulaire s'en sert pour
             * plafonner une vente pendant la frappe, plutôt que de laisser partir un envoi que
             * `TransactionRequest` refusera. Ce dernier reste le juge — il recompte contre les
             * transactions, la projection n'est qu'un raccourci d'affichage.
             */
            held: Holding::query()
                ->where('user_id', $userId)
                ->get(['wallet_id', 'asset_id', 'quantity'])
                ->map(fn (Holding $holding): HeldStockData => new HeldStockData(
                    walletId: $holding->wallet_id,
                    assetId: $holding->asset_id,
                    quantity: (float) $holding->quantity,
                ))
                ->values()
                ->all(),
        );
    }
}
