<?php

namespace App\Contexts\Income\Sources\Dividend\Infrastructure;

use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

class PortfolioPositionHistory implements PositionHistoryPort
{
    /** @return list<PositionRecordData> */
    public function transactionsFor(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('asset_id')
            ->orderBy('date')
            ->get()
            ->map(fn (Transaction $transaction): PositionRecordData => new PositionRecordData(
                assetId: (int) $transaction->asset_id,
                date: $transaction->date,
                isSell: $transaction->type === TransactionType::Sell,
                quantity: (float) $transaction->quantity,
            ))
            ->values()
            ->all();
    }

    /** @return list<int> */
    public function assetIdsFor(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('asset_id')
            ->distinct()
            ->pluck('asset_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function positionFor(int $userId, int $assetId): ?PositionSnapshotData
    {
        $rows = Holding::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $quantity = (float) $rows->sum(fn (Holding $holding): float => (float) $holding->quantity);

        /** Le prix de revient d'un actif tenu dans deux enveloppes est la moyenne pondérée des leurs. */
        $withCost = $rows->filter(fn (Holding $holding): bool => $holding->avg_cost !== null);
        $qtyWithCost = (float) $withCost->sum(fn (Holding $holding): float => (float) $holding->quantity);

        $avgCost = $qtyWithCost > 0.0
            ? (float) $withCost->sum(fn (Holding $holding): float => (float) $holding->quantity * (float) $holding->avg_cost) / $qtyWithCost
            : null;

        return new PositionSnapshotData(quantity: $quantity, avgCost: $avgCost);
    }
}
