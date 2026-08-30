<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * Une opération d'une exposition, tous ses actifs confondus. Jumelle
 * `Wealth\Datas\WealthTransactionLineData` — mêmes clés, même ordre : les deux alimentent le même
 * composant côté page, et l'instantané hors-ligne se versionne sur le JSON rendu.
 */
readonly class ClassTransactionLineData implements JsonSerializable
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
