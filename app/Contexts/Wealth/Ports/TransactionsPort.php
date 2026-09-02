<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\WealthTransactionLineData;

interface TransactionsPort
{
    /**
     * Les opérations de l'utilisateur, tous actifs confondus, la plus récente en tête.
     *
     * @return list<WealthTransactionLineData>
     */
    public function transactionsFor(int $userId): array;

    /**
     * Les opérations d'une enveloppe, la plus récente en tête. Scopée en base et non filtrée en
     * mémoire : le journal d'un compte n'a pas à charger l'historique entier du porteur.
     *
     * @return list<WealthTransactionLineData>
     */
    public function transactionsForWallet(int $userId, int $walletId): array;
}
