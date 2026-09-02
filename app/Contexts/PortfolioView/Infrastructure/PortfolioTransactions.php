<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use App\Contexts\PortfolioView\Datas\ClassTransactionLineData;
use App\Contexts\PortfolioView\Datas\TransactionLineData;
use App\Contexts\PortfolioView\Ports\TransactionsPort;

class PortfolioTransactions implements TransactionsPort
{
    public function __construct(private TransactionFlow $flow) {}

    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->orderByDesc('date')
            ->get()
            ->map(function (Transaction $transaction): TransactionLineData {
                $quantity = (float) $transaction->quantity;
                $unitPrice = (float) $transaction->unit_price;
                $fees = (float) $transaction->fees;
                $amount = $transaction->amount === null ? null : (float) $transaction->amount;
                $isSell = $transaction->type === TransactionType::Sell;

                return new TransactionLineData(
                    id: $transaction->id,
                    walletId: $transaction->wallet_id,
                    date: $transaction->date->format('Y-m-d'),
                    isSell: $isSell,
                    typeLabel: $transaction->type->getLabel(),
                    type: $transaction->type->value,
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    fees: $fees,
                    total: $this->flow->of($transaction->type, $quantity, $unitPrice, $fees, $amount),
                    auto: (bool) $transaction->auto,
                );
            })
            ->values()
            ->all();
    }

    /**
     * La jointure sert deux fins : nommer l'actif en une requête — une ligne chargeant le sien
     * rouvrirait un N+1 sur tout l'historique — et porter le partage par exposition, qui se lit sur
     * `assets.asset_class` et nulle part ailleurs. L'enveloppe, elle, se lit sur
     * `transactions.wallet_id`.
     *
     * `asset_id` est nullable en base ; une opération sans actif n'appartient à aucune exposition
     * et ne prend pas de ligne. Le journal d'une enveloppe suit la même règle que celui d'une
     * poche : c'est une liste d'opérations sur des actifs, pas un relevé de compte.
     *
     * @return list<ClassTransactionLineData>
     */
    public function transactionsForScope(int $userId, HoldingScope $scope): array
    {
        return Transaction::query()
            ->join('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->when($scope->classes !== null, fn ($query) => $query->whereIn(
                'assets.asset_class',
                array_map(fn (AssetClass $class): string => $class->value, $scope->classes),
            ))
            ->when($scope->walletId !== null, fn ($query) => $query->where('transactions.wallet_id', $scope->walletId))
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.*', 'assets.name as asset_name')
            ->get()
            ->map(function (Transaction $transaction): ClassTransactionLineData {
                $quantity = (float) $transaction->quantity;
                $unitPrice = (float) $transaction->unit_price;
                $fees = (float) $transaction->fees;
                $amount = $transaction->amount === null ? null : (float) $transaction->amount;
                $isSell = $transaction->type === TransactionType::Sell;

                return new ClassTransactionLineData(
                    id: $transaction->id,
                    walletId: $transaction->wallet_id,
                    date: $transaction->date->format('Y-m-d'),
                    assetId: (int) $transaction->asset_id,
                    assetName: (string) $transaction->getAttribute('asset_name'),
                    isSell: $isSell,
                    typeLabel: $transaction->type->getLabel(),
                    type: $transaction->type->value,
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    fees: $fees,
                    total: $this->flow->of($transaction->type, $quantity, $unitPrice, $fees, $amount),
                    auto: (bool) $transaction->auto,
                );
            })
            ->values()
            ->all();
    }
}
