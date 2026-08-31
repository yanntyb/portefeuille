<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Ce qu'une enveloppe détient d'un actif, tel que le formulaire de saisie en a besoin : de quoi
 * plafonner une vente avant l'envoi plutôt qu'après le refus du serveur.
 *
 * Le couple `(walletId, assetId)` est la clé de `holdings_projection` : un même actif tenu dans
 * deux enveloppes fait deux stocks, jamais un.
 *
 * La quantité est un flottant et non une chaîne : elle sert à comparer, pas à afficher au centième
 * près. Le serveur reste seul juge de la survente — `TransactionRequest` refait le calcul contre
 * les transactions, sans passer par cette projection.
 */
readonly class HeldStockData implements JsonSerializable
{
    public function __construct(
        public int $walletId,
        public int $assetId,
        public float $quantity,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'walletId' => $this->walletId,
            'assetId' => $this->assetId,
            'quantity' => $this->quantity,
        ];
    }
}
