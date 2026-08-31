<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Market\Enums\AssetClass;

/**
 * Un mouvement d'espèces tel que `CashLedger` le lit : une date, une enveloppe, un montant signé,
 * et de quoi dire d'où vient l'argent qui entre.
 *
 * `exposure` n'est portée que par un crédit issu du marché — vente ou dividende. Un versement
 * n'expose à rien : c'est un apport, et `isDeposit` le dit.
 */
readonly class CashMovementData
{
    public function __construct(
        public string $date,
        public int $walletId,
        public float $delta,
        public ?AssetClass $exposure,
        public bool $isDeposit,
        public bool $auto,
    ) {}
}
