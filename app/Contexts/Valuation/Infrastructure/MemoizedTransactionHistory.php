<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

/**
 * Une même requête résout plusieurs propriétés différées — performances, séries d'évolution —
 * qui repartent chacune du même historique de transactions. Le décorateur ne le lit qu'une fois.
 *
 * La portée est celle de la requête : le service est enregistré en `scoped()`, jamais en
 * `singleton()`, pour que la mémoire ne survive pas d'une requête à l'autre.
 */
class MemoizedTransactionHistory implements TransactionHistoryPort
{
    /** @var array<int, list<TransactionRecordData>> */
    private array $byUser = [];

    public function __construct(private TransactionHistoryPort $inner) {}

    /** @return list<TransactionRecordData> */
    public function forUser(int $userId): array
    {
        return $this->byUser[$userId] ??= $this->inner->forUser($userId);
    }
}
