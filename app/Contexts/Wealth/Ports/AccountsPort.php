<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\WalletClassSliceData;
use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Datas\WealthHoldingData;

interface AccountsPort
{
    /**
     * Les enveloppes de détention de l'utilisateur, la plus grosse en tête. Une enveloppe sans
     * position n'a pas de ligne.
     *
     * @return list<WealthAccountData>
     */
    public function accountsFor(int $userId): array;

    /** L'enveloppe demandée ; `null` quand elle n'existe pas ou qu'un autre porteur la tient. */
    public function accountFor(int $userId, int $walletId): ?WealthAccountData;

    /**
     * Les positions tenues dans l'enveloppe, toutes classes confondues.
     *
     * @return list<WealthHoldingData>
     */
    public function positionsFor(int $userId, int $walletId): array;

    /**
     * La ventilation de l'enveloppe par classe d'actif, la plus grosse part en tête.
     *
     * @return list<WalletClassSliceData>
     */
    public function breakdownFor(int $userId, int $walletId): array;
}
