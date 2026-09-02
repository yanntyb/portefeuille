<?php

namespace App\Contexts\Valuation\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\TransactionType;
use Illuminate\Support\Carbon;

readonly class TransactionRecordData
{
    /**
     * `assetId` est nullable : un versement ou un retrait ne détient aucune position. `isSell`
     * reste à côté de `type` pour ne rien casser de l'existant, qui le lit directement.
     *
     * `amount` et `exposure` accompagnent les mouvements d'espèces sans quantité (versement,
     * retrait, dividende) : `amount` est le montant saisi, `exposure` la classe d'actif de
     * l'instrument concerné quand il y en a un.
     */
    public function __construct(
        public Carbon $date,
        public ?int $assetId,
        /**
         * L'enveloppe qui tient l'opération. Non nullable, versements compris : une transaction
         * appartient toujours à un compte, c'est ce qui permet à `BuildExposureSeries` de filtrer
         * une série par enveloppe sans laisser échapper le cash.
         */
        public int $walletId,
        public TransactionType $type,
        public bool $isSell,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
        public ?float $amount = null,
        public ?AssetClass $exposure = null,
    ) {}
}
