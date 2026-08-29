<?php

namespace App\Contexts\Income\Sources\Dividend\Infrastructure;

use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

class PortfolioPositionHistory implements PositionHistoryPort
{
    public function __construct(private GetPortfolioPositions $positions) {}

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
        $position = ($this->positions)($userId)[$assetId] ?? null;

        return $position === null ? null : new PositionSnapshotData($position->quantity, $position->avgCost);
    }

    /** @return array<int, PositionSnapshotData> */
    public function positionsFor(int $userId): array
    {
        return array_map(
            fn (PositionLineData $position): PositionSnapshotData => new PositionSnapshotData(
                quantity: $position->quantity,
                avgCost: $position->avgCost,
            ),
            ($this->positions)($userId),
        );
    }
}
