<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Portfolio\Actions\CreateTransaction;
use App\Contexts\Portfolio\Datas\TransactionInputData;
use Illuminate\Http\RedirectResponse;

/**
 * Enregistrement d'une opération saisie.
 *
 * Redirection et non réponse vide, contrairement à `StartSyncController` : celui-ci répond à une
 * visite Inertia, et le client la fait partielle (`only`). Une réponse partielle ne porte jamais
 * `deferredProps` — la métadonnée est réservée aux visites complètes — donc les groupes différés de
 * la page ne repartent pas, et les props demandées reviennent à jour en un seul aller-retour.
 */
class StoreTransactionController
{
    public function __construct(private CreateTransaction $create) {}

    public function __invoke(TransactionRequest $request): RedirectResponse
    {
        ($this->create)(
            (int) auth()->id(),
            TransactionInputData::fromValidated($request->validated()),
        );

        return back();
    }
}
