<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use Illuminate\Support\Collection;

/**
 * Les mouvements d'espèces de l'utilisateur, traduits en `CashMovementData` pour `CashLedger`.
 *
 * Site unique de la traduction Eloquent vers le format que le service pur consomme : jointure sur
 * `assets` pour connaître l'exposition, montant signé par `TransactionFlow::cashDelta()`.
 *
 * Mémoïsée par utilisateur ET par `$includeAuto` pour la durée de la requête, sur le modèle de
 * `GetPortfolioOverview` : `RecomputeCashDeposits` lit les deux variantes dans la même requête HTTP
 * (l'observateur, puis potentiellement un autre appelant plus tard), et doit pouvoir invalider ce
 * cache par `forget()` après avoir écrit.
 */
class GetCashMovements
{
    /**
     * @var array<int, array<int, list<CashMovementData>>>
     */
    private array $movementsByUser = [];

    public function __construct(private TransactionFlow $flow) {}

    /**
     * Sans exclusion par défaut : `RecomputeCashDeposits` demande `includeAuto: false` pour
     * repartir des seuls faits saisis, sans quoi les lignes déduites périmées se reconduiraient.
     *
     * @return list<CashMovementData>
     */
    public function __invoke(int $userId, bool $includeAuto = true): array
    {
        $key = $includeAuto ? 1 : 0;

        return $this->movementsByUser[$userId][$key] ??= $this->readMovements($userId, $includeAuto);
    }

    /** Invalide le cache d'un utilisateur : à appeler avant toute relecture qui suit une écriture. */
    public function forget(int $userId): void
    {
        unset($this->movementsByUser[$userId]);
    }

    /** @return list<CashMovementData> */
    private function readMovements(int $userId, bool $includeAuto): array
    {
        $query = Transaction::query()
            ->select('transactions.*', 'assets.asset_class as asset_class')
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId);

        if (! $includeAuto) {
            $query->where('transactions.auto', false);
        }

        /** @var Collection<int, Transaction> $rows */
        $rows = $query->orderBy('transactions.date')->get();

        return $rows
            ->map(fn (Transaction $row): CashMovementData => new CashMovementData(
                date: $row->date->format('Y-m-d'),
                walletId: (int) $row->wallet_id,
                delta: $this->flow->cashDelta(
                    $row->type,
                    $row->quantity !== null ? (float) $row->quantity : null,
                    $row->unit_price !== null ? (float) $row->unit_price : null,
                    (float) $row->fees,
                    $row->amount !== null ? (float) $row->amount : null,
                ),
                exposure: $row->getAttribute('asset_class') !== null
                    ? AssetClass::from($row->getAttribute('asset_class'))
                    : null,
                isDeposit: $row->type === TransactionType::Deposit,
                auto: (bool) $row->auto,
                isWithdrawal: $row->type === TransactionType::Withdrawal,
            ))
            ->all();
    }
}
