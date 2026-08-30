<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Une opération du patrimoine, tous actifs confondus. Jumelle `MarketView\TransactionLineData` et
 * lui ajoute l'actif : la fiche n'a pas à le nommer, le tableau de bord si.
 */
readonly class WealthTransactionLineData implements JsonSerializable
{
    public function __construct(
        public string $date,
        public int $assetId,
        public string $assetName,
        public bool $isSell,
        public string $typeLabel,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
        public float $total,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'isSell' => $this->isSell,
            'typeLabel' => $this->typeLabel,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'fees' => $this->fees,
            'total' => $this->total,
        ];
    }
}
