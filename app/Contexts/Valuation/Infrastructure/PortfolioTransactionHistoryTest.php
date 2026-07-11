<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

it('maps a user transactions to records ordered by date', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'fees' => 2, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 4, 'unit_price' => 150, 'date' => '2026-02-01',
    ]);

    $records = app(TransactionHistoryPort::class)->forUser($user->id);

    expect($records)->toHaveCount(2)
        ->and($records[0]->date->format('Y-m-d'))->toBe('2026-01-01')
        ->and($records[0]->isSell)->toBeFalse()
        ->and($records[0]->quantity)->toBe(10.0)
        ->and($records[0]->unitPrice)->toBe(100.0)
        ->and($records[0]->fees)->toBe(2.0)
        ->and($records[1]->isSell)->toBeTrue()
        ->and($records[1]->assetId)->toBe($asset->id);
});
