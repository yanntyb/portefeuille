<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Portfolio\Actions\ConfirmDividend;
use Illuminate\Http\RedirectResponse;

/**
 * Encaissement d'un dividende attendu, saisi depuis la fiche de revenu.
 *
 * Redirection comme `StoreTransactionController` : le client fait sa visite partielle.
 */
class ConfirmDividendController
{
    public function __construct(private ConfirmDividend $confirm) {}

    public function __invoke(ConfirmDividendRequest $request): RedirectResponse
    {
        ($this->confirm)(
            (int) auth()->id(),
            (int) $request->validated('walletId'),
            (int) $request->validated('assetId'),
            (string) $request->validated('exDate'),
            (float) $request->validated('amount'),
        );

        return back();
    }
}
