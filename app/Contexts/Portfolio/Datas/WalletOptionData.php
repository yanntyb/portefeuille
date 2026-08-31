<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Une enveloppe telle qu'un formulaire la propose. `accountTypeLabel` accompagne le nom parce que
 * deux comptes peuvent porter le même intitulé sous deux régimes, et parce que c'est ce qui permet
 * au front d'avertir d'une inéligibilité — avertir, jamais interdire.
 */
readonly class WalletOptionData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $accountType,
        public string $accountTypeLabel,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'accountType' => $this->accountType,
            'accountTypeLabel' => $this->accountTypeLabel,
        ];
    }
}
