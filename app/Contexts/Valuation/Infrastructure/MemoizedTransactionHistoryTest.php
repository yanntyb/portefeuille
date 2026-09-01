<?php

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Infrastructure\MemoizedTransactionHistory;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use Illuminate\Support\Carbon;

function countingTransactionHistory(int &$calls): TransactionHistoryPort
{
    return new class($calls) implements TransactionHistoryPort
    {
        public function __construct(private int &$calls) {}

        public function forUser(int $userId): array
        {
            $this->calls++;

            return [new TransactionRecordData(
                date: Carbon::parse('2026-01-01'),
                assetId: $userId,
                type: TransactionType::Buy,
                isSell: false,
                quantity: 1.0,
                unitPrice: 10.0,
                fees: 0.0,
                cashDelta: -10.0,
            )];
        }
    };
}

it('ne lit l\'historique d\'un utilisateur qu\'une fois', function () {
    $calls = 0;
    $history = new MemoizedTransactionHistory(countingTransactionHistory($calls));

    $first = $history->forUser(7);
    $second = $history->forUser(7);

    expect($calls)->toBe(1)
        ->and($second)->toBe($first);
});

it('garde un historique distinct par utilisateur', function () {
    $calls = 0;
    $history = new MemoizedTransactionHistory(countingTransactionHistory($calls));

    expect($history->forUser(1)[0]->assetId)->toBe(1)
        ->and($history->forUser(2)[0]->assetId)->toBe(2)
        ->and($calls)->toBe(2);
});
