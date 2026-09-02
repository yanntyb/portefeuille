<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Une opération du patrimoine, tous actifs confondus. Jumelle `PortfolioView\TransactionLineData` et
 * lui ajoute l'actif : la fiche n'a pas à le nommer, le tableau de bord si.
 *
 * `assetId`/`assetName` sont nullables ici, à la différence de `ClassTransactionLineData` : un
 * versement ou un retrait n'a pas d'actif, et le tableau de bord les affiche quand même — c'est le
 * seul des trois journaux à les porter.
 *
 * `type` distingue les cinq natures d'opération, `isSell` restant pour ne rien casser côté
 * consommateurs qui ne lisaient que le sens d'un ordre. `auto` dit qu'une ligne a été déduite par le
 * système plutôt que saisie.
 */
readonly class WealthTransactionLineData implements JsonSerializable
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
