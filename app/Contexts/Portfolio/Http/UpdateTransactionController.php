<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Portfolio\Actions\UpdateTransaction;
use App\Contexts\Portfolio\Datas\TransactionInputData;
use App\Contexts\Portfolio\Support\UserTransactions;
use Illuminate\Http\RedirectResponse;

class UpdateTransactionController
{
    public function __construct(
        private UpdateTransaction $update,
        private UserTransactions $transactions,
    ) {}

    public function __invoke(TransactionRequest $request, int $id): RedirectResponse
    {
        /**
         * 404 et non 403, comme `AssetController` sur un actif inconnu : la ligne d'un autre
         * utilisateur est introuvable, et le code ne révèle pas qu'elle existe.
         */
        $transaction = $this->transactions->find((int) auth()->id(), $id);

        if ($transaction === null) {
            abort(404);
        }

        /**
         * Une ligne déduite ne se corrige pas : `RecomputeCashDeposits` efface toutes les lignes
         * `auto` de l'enveloppe et les rejoue à chaque écriture, si bien que la correction était
         * enregistrée puis détruite par l'observateur dans la même requête — 302 sans un mot. Le
         * front bride déjà l'accès ; la route ne l'était pas.
         *
         * 404 et non 403, comme la ligne d'un autre : le code ne dit pas ce qui existe.
         */
        if ($transaction->auto) {
            abort(404);
        }

        ($this->update)($transaction, TransactionInputData::fromValidated($request->validated()));

        return back();
    }
}
