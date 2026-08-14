<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function instrumentWithTransactions(int $count): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    foreach (range(1, $count) as $index) {
        Transaction::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'date' => sprintf('2026-01-%02d', $index),
            'quantity' => 1,
            'unit_price' => 100,
            'fees' => 0,
        ]);
    }

    return ['user' => $user, 'instrument' => $asset];
}

it('keeps the transactions folded behind their count', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithTransactions(13);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertSee('Transactions (13)')
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 13)
        ->assertSee('2026-01-01')
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('folds the transactions even when there are only a few', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithTransactions(4);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 4)
        ->assertNoJavaScriptErrors();
});
