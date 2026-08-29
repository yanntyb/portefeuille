<?php

use App\Contexts\Income\Services\ReceiptTotals;
use Illuminate\Support\Carbon;

function incomeReceipt(string $date, float $amount, string $source = 'dividend'): array
{
    return ['date' => Carbon::parse($date), 'amount' => $amount, 'source' => $source];
}

it('totalise et ventile par origine', function () {
    $summary = (new ReceiptTotals)->summarize([
        incomeReceipt('2026-03-05', 8.0),
        incomeReceipt('2026-04-05', 12.0, 'rent'),
    ], Carbon::parse('2025-08-29'));

    expect($summary)->toBe([
        'total' => 20.0,
        'last12Months' => 20.0,
        'bySource' => ['dividend' => 8.0, 'rent' => 12.0],
    ]);
});

it('exclut des douze derniers mois ce qui les précède', function () {
    $summary = (new ReceiptTotals)->summarize([
        incomeReceipt('2024-03-05', 5.0),
        incomeReceipt('2026-03-05', 8.0),
    ], Carbon::parse('2025-08-29'));

    expect($summary['total'])->toBe(13.0)
        ->and($summary['last12Months'])->toBe(8.0);
});

it('groupe par année civile croissante', function () {
    expect((new ReceiptTotals)->byYear([
        incomeReceipt('2026-03-05', 8.0),
        incomeReceipt('2025-03-05', 5.0, 'rent'),
        incomeReceipt('2025-06-05', 5.0, 'rent'),
    ]))->toBe([
        2025 => ['rent' => 10.0],
        2026 => ['dividend' => 8.0],
    ]);
});

it('rend des totaux vides sans aucun reçu', function () {
    expect((new ReceiptTotals)->summarize([], Carbon::parse('2025-08-29')))->toBe([
        'total' => 0.0,
        'last12Months' => 0.0,
        'bySource' => [],
    ]);
});
