<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Portfolio\Actions\ConfirmDividend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
        try {
            ($this->confirm)(
                (int) auth()->id(),
                (int) $request->validated('walletId'),
                (int) $request->validated('assetId'),
                (string) $request->validated('exDate'),
                (float) $request->validated('amount'),
            );
        } catch (RuntimeException $e) {
            /**
             * Un double clic ou un retour arrière rejoue le même encaissement : `ConfirmDividend`
             * le refuse déjà, mais par une exception nue, qui rendrait autrement une 500 brute. On
             * la traduit en erreur de session sur `exDate` — c'est la date du détachement qui rend
             * la ligne en double, pas le montant saisi.
             */
            throw ValidationException::withMessages(['exDate' => $e->getMessage()]);
        }

        return back();
    }
}
