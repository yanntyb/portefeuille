<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * Une opération sur un actif. `id` et `walletId` ouvrent l'édition depuis la liste : sans l'un on
 * ne sait pas quelle ligne réécrire, sans l'autre on la réécrirait sur la mauvaise enveloppe — la
 * même quantité du même actif peut être tenue dans deux comptes.
 *
 * Les deux sont en tête et non près de leurs voisins de sens, parce que les trois jumelles doivent
 * porter les mêmes clés dans le même ordre (`.ai/rules/market-view.md`) et que celle-ci n'a pas
 * d'`assetId` : la tête est la seule position que les trois peuvent partager.
 *
 * `type` distingue les cinq natures d'opération, `isSell` restant pour ne rien casser côté
 * consommateurs qui ne lisaient que le sens d'un ordre. `auto` dit qu'une ligne a été déduite par le
 * système plutôt que saisie : le front s'en sert pour n'offrir aucune correction sur ces lignes-là,
 * qui seraient de toute façon réécrites à la prochaine reconstruction.
 */
readonly class TransactionLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $walletId,
        public string $date,
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
