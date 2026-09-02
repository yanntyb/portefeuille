<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Une opération d'une exposition, tous ses actifs confondus. Jumelle
 * `Wealth\Datas\WealthTransactionLineData` — mêmes clés, même ordre : les deux alimentent le même
 * composant côté page, et l'instantané hors-ligne se versionne sur le JSON rendu.
 *
 * Filtrée par classe d'actif (`assets.asset_class`), elle ne porte donc jamais de ligne sans actif :
 * `assetId`/`assetName` restent non nullables ici, à la différence de la jumelle du patrimoine.
 *
 * `type` distingue les cinq natures d'opération, `isSell` restant pour ne rien casser côté
 * consommateurs qui ne lisaient que le sens d'un ordre. `auto` dit qu'une ligne a été déduite par le
 * système plutôt que saisie.
 */
readonly class ClassTransactionLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $walletId,
        public string $date,
        public int $assetId,
        public string $assetName,
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
