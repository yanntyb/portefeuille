<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Market\Enums\AssetClass;

/**
 * Un mouvement d'espèces tel que `CashLedger` le lit : une date, une enveloppe, un montant signé,
 * et de quoi dire d'où vient l'argent qui entre.
 *
 * `exposure` n'est portée que par un crédit issu du marché — vente ou dividende. Un versement
 * n'expose à rien : c'est un apport, et `isDeposit` le dit. `isWithdrawal` marque le débit inverse :
 * un retrait se retranche **en entier** des apports nets, quelle que soit l'étiquette des crédits
 * qu'il consomme, et sans jamais s'imputer à une exposition. N'en retrancher que la part prise à un
 * apport laissait un retrait payé par le produit d'une vente sans effet sur le total.
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
        public bool $isWithdrawal = false,
    ) {}
}
