<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Une opération d'un périmètre de positions — une exposition, une enveloppe. Jumelle
 * `Wealth\Datas\WealthTransactionLineData` — mêmes clés, même ordre : les deux alimentent le même
 * composant côté page, et l'instantané hors-ligne se versionne sur le JSON rendu.
 *
 * `assetId`/`assetName` sont nullables, et c'est le périmètre qui décide si le cas se présente :
 * filtrée par classe (`assets.asset_class`), la liste ne porte aucune ligne sans actif — un
 * versement n'appartient à aucune exposition ; filtrée par enveloppe, elle porte les versements et
 * retraits du compte, qui lui appartiennent bel et bien. Même asymétrie que
 * `Valuation\Services\ScopedTransactions`, pour la même raison.
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
