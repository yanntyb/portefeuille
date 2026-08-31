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
 */
readonly class WalletOptionData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $broker,
        public string $accountType,
        public string $accountTypeLabel,
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
        ];
    }
}
