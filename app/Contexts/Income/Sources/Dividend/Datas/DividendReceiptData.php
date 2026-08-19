<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use JsonSerializable;

/**
 * Détail d'un dividende perçu. Seule la fiche instrument le consomme : le noyau du contexte
 * Income n'en voit que la forme générique, `IncomeReceiptData`.
 */
readonly class DividendReceiptData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $exDate,
        public float $quantity,
        public float $amountPerShare,
        public float $amount,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'exDate' => $this->exDate,
            'quantity' => $this->quantity,
            'amountPerShare' => $this->amountPerShare,
            'amount' => $this->amount,
        ];
    }
}
