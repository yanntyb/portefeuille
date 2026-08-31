<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetPositionStock;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * @return array{quantity: float, avgCost: float}
 */
function stockOf(int $userId, int $assetId, int $walletId, ?int $ignoring = null): array
{
    return app(GetPositionStock::class)($userId, $assetId, $walletId, $ignoring);
}

it('nets the sells against the buys', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    Transaction::factory()->buy()->for($user)->for($wallet)->create([
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $bought = stockOf($user->id, $instrument->id, $wallet->id)['quantity'];

    Transaction::factory()->sell()->for($user)->for($wallet)->create([
        'asset_id' => $instrument->id,
        'quantity' => 3,
        'unit_price' => 120,
        'fees' => 0,
    ]);

    expect(stockOf($user->id, $instrument->id, $wallet->id)['quantity'])->toBe($bought - 3.0);
});

it('carries the purchase fees into the average cost', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $instrument = Instrument::factory()->create(['ticker' => 'FEE.PA']);

    Transaction::factory()->buy()->for($user)->for($wallet)->create([
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 50,
    ]);

    /** 1 000 € de titres plus 50 € de courtage sur 10 titres : le prix de revient vaut 105. */
    expect(stockOf($user->id, $instrument->id, $wallet->id))
        ->toBe(['quantity' => 10.0, 'avgCost' => 105.0]);
});

it('ignores one transaction when asked to', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $instrument = Instrument::factory()->create(['ticker' => 'IGN.PA']);

    Transaction::factory()->buy()->for($user)->for($wallet)->create([
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->sell()->for($user)->for($wallet)->create([
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'unit_price' => 120,
        'fees' => 0,
    ]);

    /**
     * L'exclusion sert le contrôle de survente à l'édition : porter cette vente de 4 à 5 doit se
     * comparer aux 10 titres achetés, pas aux 6 qui restent une fois ses propres 4 déduits.
     */
    expect(stockOf($user->id, $instrument->id, $wallet->id)['quantity'])->toBe(6.0)
        ->and(stockOf($user->id, $instrument->id, $wallet->id, $sell->id)['quantity'])->toBe(10.0);
});

it('keeps two wallets of the same asset apart', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    $elsewhere = Wallet::factory()->for($user)->create(['name' => 'Ailleurs']);

    Transaction::factory()->buy()->for($user)->for($elsewhere)->create([
        'asset_id' => $instrument->id,
        'quantity' => 7,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    /** La clé de `holdings_projection` est le couple : un stock est propre à son enveloppe. */
    expect(stockOf($user->id, $instrument->id, $elsewhere->id)['quantity'])->toBe(7.0);
});

it('reads an empty stock when nothing was ever bought', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    expect(stockOf($user->id, 999_999, $wallet->id))
        ->toBe(['quantity' => 0.0, 'avgCost' => 0.0]);
});
