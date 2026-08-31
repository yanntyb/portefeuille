<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Portfolio\Actions\GetTransactionFormOptions;
use Illuminate\Http\JsonResponse;

/**
 * De quoi garnir le formulaire de saisie, chargé à la première ouverture de la modale.
 *
 * Une route à part et non une prop de page : la modale vit sur trois pages, soit six routes, et ces
 * listes ne servent qu'à qui saisit. Une prop les mettrait dans le document initial de chaque
 * visite, donc dans le cache du service worker, et il faudrait décider de leur place dans
 * l'instantané hors-ligne — où elles n'ont rien à faire, la saisie y étant bloquée.
 *
 * Elles ne sont donc pas non plus servies périmées : `classifyRequest()` les laisse passer sans
 * cache, comme `/instantane`.
 */
class TransactionOptionsController
{
    public function __construct(private GetTransactionFormOptions $options) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(($this->options)((int) auth()->id()));
    }
}
