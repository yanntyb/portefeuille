<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\WealthAccountData;

interface AccountsPort
{
    /**
     * Les enveloppes de détention de l'utilisateur, la plus grosse en tête. Une enveloppe sans
     * position n'a pas de ligne.
     *
     * Le patrimoine ne lit les enveloppes qu'en liste, pour ses cartes repliées : ce qu'une
     * enveloppe tient et vaut dans le détail se lit sur sa page, que sert `PortfolioView`.
     *
     * @return list<WealthAccountData>
     */
    public function accountsFor(int $userId): array;
}
