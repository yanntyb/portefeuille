<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\PortfolioView\Datas\AccountRowData;

/**
 * Ce qu'une enveloppe déclare d'elle-même : son régime fiscal, son ancienneté, ses espèces, les
 * actifs qu'elle n'admet pas. Rien de valorisé — cela se lit par `PortfolioOverviewPort`, avec le
 * périmètre de l'enveloppe.
 *
 * Un port à part parce que c'est une autre question : les autres ports pèsent des positions, celui
 * -ci lit les règles du compte qui les tient.
 */
interface AccountsPort
{
    /** `null` quand l'enveloppe n'existe pas ou qu'un autre porteur la tient : la page en fait un 404. */
    public function accountFor(int $userId, int $walletId): ?AccountRowData;
}
