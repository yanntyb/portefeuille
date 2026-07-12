<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;

it('aggregates two holdings into portfolio trailing performances', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $a = Instrument::factory()->create();
    $b = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $a->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2024-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $b->id,
        'quantity' => 5, 'unit_price' => 100, 'date' => '2024-01-01',
    ]);
    foreach (['2024-01-01', '2024-07-01', '2025-01-01', '2025-07-01', '2026-01-01', '2026-04-01', '2026-06-01'] as $date) {
        Price::factory()->create(['asset_id' => $a->id, 'date' => $date, 'close' => 100]);
        Price::factory()->create(['asset_id' => $b->id, 'date' => $date, 'close' => 100]);
    }
    Price::factory()->create(['asset_id' => $a->id, 'date' => '2026-07-01', 'close' => 120]);
    Price::factory()->create(['asset_id' => $b->id, 'date' => '2026-07-01', 'close' => 120]);

    $performances = app(BuildPortfolioPerformances::class)($user->id);

    // Portefeuille début 2026-07-01: (10+5)*100 = 1500 ; fin (10+5)*120 = 1800.
    // Historique 2024-01-01 -> 2026-07-01 => 2 années pleines.
    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['YTD', '1M', '3M', '6M', '1Y', '2Y'])
        ->and($performances[0]->key)->toBe('YTD')
        ->and($performances[5]->pct)->toBe(20.0);
});

it('returns no performances without any transaction', function () {
    $user = User::factory()->create();

    expect(app(BuildPortfolioPerformances::class)($user->id))->toBe([]);
});
