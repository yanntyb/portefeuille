<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Une enveloppe telle qu'un formulaire la propose. `accountTypeLabel` accompagne le nom parce que
 * deux comptes peuvent porter le même intitulé sous deux régimes, et parce que c'est ce qui permet
 * au front d'avertir d'une inéligibilité — avertir, jamais interdire.
 *
 * `broker` voyage avec, nullable comme en base : c'est lui qui distingue deux enveloppes de même
 * régime dans la liste, le nom du compte ne prenant le relais que faute d'établissement — même
 * repli que les cartes d'enveloppes du tableau de bord.
 *
 * `cashBalance` est le solde d'espèces d'aujourd'hui, qui plafonne un retrait pendant la frappe.
 * Zéro et non `null` sur une enveloppe sans mouvement : le compte existe, il ne porte rien — c'est
 * un plafond, pas une inconnue. Le solde d'un jour passé peut être plus bas ; `TransactionRequest`
 * reste le juge, il recompte à la date saisie.
 */
readonly class WalletOptionData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $broker,
        public string $accountType,
        public string $accountTypeLabel,
        public float $cashBalance,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'broker' => $this->broker,
            'accountType' => $this->accountType,
            'accountTypeLabel' => $this->accountTypeLabel,
            'cashBalance' => $this->cashBalance,
        ];
    }
}
