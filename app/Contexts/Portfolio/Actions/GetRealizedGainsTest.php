<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetRealizedGains;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/** Un aller-retour : l'achat pose le prix de revient, la vente réalise l'écart. */
function roundTrip(User $user, Instrument $asset, float $buyPrice, float $sellPrice, float $quantity): void
{
    $wallet = Wallet::factory()->for($user)->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'type' => TransactionType::Buy,
        'date' => '2026-01-05',
        'quantity' => $quantity,
        'unit_price' => $buyPrice,
        'fees' => 0,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'type' => TransactionType::Sell,
        'date' => '2026-02-05',
        'quantity' => $quantity,
        'unit_price' => $sellPrice,
        'fees' => 0,
    ]);
}

it('sums the realized gain of each asset', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();

    roundTrip($user, $asset, buyPrice: 100, sellPrice: 120, quantity: 10);

    expect(app(GetRealizedGains::class)($user->id))->toBe([$asset->id => 200.0]);
});

it('nets a loss against a gain on the same asset', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();

    roundTrip($user, $asset, buyPrice: 100, sellPrice: 120, quantity: 10);
    roundTrip($user, $asset, buyPrice: 100, sellPrice: 90, quantity: 5);

    // +200 sur le premier aller-retour, -50 sur le second, chacun dans son enveloppe.
    expect(app(GetRealizedGains::class)($user->id))->toBe([$asset->id => 150.0]);
});

it('keeps the realized gain of an asset that is no longer held', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();

    roundTrip($user, $asset, buyPrice: 100, sellPrice: 120, quantity: 10);

    // La ligne est soldée : sans son gain réalisé, l'aller-retour disparaîtrait du bilan.
    expect($user->holdings()->sum('quantity'))->toEqual(0)
        ->and(app(GetRealizedGains::class)($user->id))->toBe([$asset->id => 200.0]);
});

it('restricts the total to the requested exposures', function () {
    $user = User::factory()->create();
    $stock = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    $crypto = Instrument::factory()->ofType(InstrumentType::Crypto)->create();

    roundTrip($user, $stock, buyPrice: 100, sellPrice: 120, quantity: 10);
    roundTrip($user, $crypto, buyPrice: 100, sellPrice: 150, quantity: 4);

    $action = app(GetRealizedGains::class);

    expect($action->totalFor($user->id, HoldingScope::ofClasses([AssetClass::Equity])))->toBe(200.0)
        ->and($action->totalFor($user->id, HoldingScope::ofClasses([AssetClass::Crypto])))->toBe(200.0)
        ->and($action->totalFor($user->id, HoldingScope::all()))->toBe(400.0);
});

it('ignores the transactions of another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();

    roundTrip($other, $asset, buyPrice: 100, sellPrice: 120, quantity: 10);

    expect(app(GetRealizedGains::class)($user->id))->toBe([]);
});

it('reports nothing on a portfolio without a single sale', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    $wallet = Wallet::factory()->for($user)->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'type' => TransactionType::Buy,
        'date' => '2026-01-05',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    expect(app(GetRealizedGains::class)($user->id))->toBe([])
        ->and(app(GetRealizedGains::class)->totalFor($user->id, HoldingScope::all()))->toBe(0.0);
});
