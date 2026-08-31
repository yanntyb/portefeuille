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

        ($this->update)($transaction, TransactionInputData::fromValidated($request->validated()));

        return back();
    }
}
