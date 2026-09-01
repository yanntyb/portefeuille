<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use JsonSerializable;

/**
 * Détail d'un dividende perçu. Seule la fiche instrument le consomme : le noyau du contexte
 * Income n'en voit que la forme générique, `IncomeReceiptData`.
 *
 * `quantity` et `amountPerShare` sont nulles, et non zéro, quand elles ne sont pas connues : un
 * dividende confirmé dont `ConfirmedDividendSubstitution` ne retrouve pas le détachement dérivé
 * n'a personne pour les dire, et « 0 titre × 0 € » se lirait comme un fait mesuré à côté d'un
 * montant bien réel. `amount`, lui, est toujours connu — c'est ce qui a été encaissé.
 */
readonly class DividendReceiptData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public int $walletId,
        public string $exDate,
        public ?float $quantity,
        public ?float $amountPerShare,
        public float $amount,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'walletId' => $this->walletId,
            'exDate' => $this->exDate,
            'quantity' => $this->quantity,
            'amountPerShare' => $this->amountPerShare,
            'amount' => $this->amount,
        ];
    }
}
