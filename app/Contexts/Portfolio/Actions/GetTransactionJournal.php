<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\TransactionLineData;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le journal d'opérations d'un porteur, la plus récente en tête, chaque ligne nommant son actif.
 *
 * Un seul site de lecture pour le tableau de bord, les expositions, les enveloppes et les fiches :
 * trois adaptateurs recopiaient cette requête. Le périmètre est l'inverse de celui de la série
 * (`Valuation\Services\ScopedTransactions`) sur la classe : la série garde les mouvements sans actif
 * pour tenir le cash, le journal d'une exposition les écarte — un versement n'a pas de classe ; le
 * `whereIn` sur la jointure les élimine de lui-même. Par enveloppe, les deux les gardent : le cash
 * est tenu par wallet.
 */
class GetTransactionJournal
{
    public function __construct(private TransactionFlow $flow) {}

    /** @return list<TransactionLineData> */
    public function __invoke(int $userId, ?HoldingScope $scope = null): array
    {
        $scope ??= HoldingScope::all();

        return $this->read(
            $this->query($userId)
                ->when($scope->classes !== null, fn (Builder $query): Builder => $query->whereIn(
                    'assets.asset_class',
                    array_map(fn (AssetClass $class): string => $class->value, $scope->classes),
                ))
                ->when($scope->walletId !== null, fn (Builder $query): Builder => $query
                    ->where('transactions.wallet_id', $scope->walletId)),
        );
    }

    /** @return list<TransactionLineData> */
    public function forAsset(int $userId, int $assetId): array
    {
        return $this->read($this->query($userId)->where('transactions.asset_id', $assetId));
    }

    private function query(int $userId): Builder
    {
        return Transaction::query()
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.*', 'assets.name as asset_name');
    }

    /** @return list<TransactionLineData> */
    private function read(Builder $query): array
    {
        return $query->get()
            ->map(fn (Transaction $transaction): TransactionLineData => $this->line($transaction))
            ->values()
            ->all();
    }

    private function line(Transaction $transaction): TransactionLineData
    {
        $quantity = (float) $transaction->quantity;
        $unitPrice = (float) $transaction->unit_price;
        $fees = (float) $transaction->fees;
        $amount = $transaction->amount === null ? null : (float) $transaction->amount;
        $assetName = $transaction->getAttribute('asset_name');

        return new TransactionLineData(
            id: $transaction->id,
            walletId: $transaction->wallet_id,
            date: $transaction->date->format('Y-m-d'),
            assetId: $transaction->asset_id === null ? null : (int) $transaction->asset_id,
            assetName: $assetName === null ? null : (string) $assetName,
            isSell: $transaction->type === TransactionType::Sell,
            typeLabel: $transaction->type->getLabel(),
            type: $transaction->type->value,
            quantity: $quantity,
            unitPrice: $unitPrice,
            fees: $fees,
            total: $this->flow->of($transaction->type, $quantity, $unitPrice, $fees, $amount),
            auto: (bool) $transaction->auto,
        );
    }
}
