<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Une ligne du journal d'opérations, telle que la lisent le tableau de bord, une exposition, une
 * enveloppe ou une fiche instrument. `assetId` et `assetName` sont nuls sur un mouvement
 * d'espèces. `total` vient de `TransactionFlow`, seul site du montant d'une ligne.
 */
readonly class TransactionLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $walletId,
        public string $date,
        public ?int $assetId,
        public ?string $assetName,
        public bool $isSell,
        public string $typeLabel,
        public string $type,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
        public float $total,
        public bool $auto,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'walletId' => $this->walletId,
            'date' => $this->date,
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'isSell' => $this->isSell,
            'typeLabel' => $this->typeLabel,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'fees' => $this->fees,
            'total' => $this->total,
            'auto' => $this->auto,
        ];
    }
}
