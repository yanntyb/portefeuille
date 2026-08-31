<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Portfolio\Actions\DeleteTransaction;
use App\Contexts\Portfolio\Support\UserTransactions;
use Illuminate\Http\RedirectResponse;

class DeleteTransactionController
{
    public function __construct(
        private DeleteTransaction $delete,
        private UserTransactions $transactions,
    ) {}

    public function __invoke(int $id): RedirectResponse
    {
        $transaction = $this->transactions->find((int) auth()->id(), $id);

        if ($transaction === null) {
            abort(404);
        }

        ($this->delete)($transaction);

        return back();
    }
}
