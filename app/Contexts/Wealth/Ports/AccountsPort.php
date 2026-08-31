<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\WealthAccountData;

interface AccountsPort
{
    /**
     * Les enveloppes de détention de l'utilisateur, la plus grosse en tête. Une enveloppe sans
     * position n'a pas de ligne.
     *
     * @return list<WealthAccountData>
     */
    public function accountsFor(int $userId): array;
}
