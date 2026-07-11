<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentDetail;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('returns null for an unknown instrument', function () {
    $user = User::factory()->create();

    expect(app(GetInstrumentDetail::class)($user->id, 999))->toBeNull();
});

it('composes a held instrument with position, gain, transactions and sectors', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);
    SectorAllocation::factory()->create(['asset_id' => $asset->id, 'sector' => Sector::Technology, 'weight' => 1]);

    $detail = app(GetInstrumentDetail::class)($user->id, $asset->id);

    expect($detail->name)->toBe('ACME');
    expect($detail->lastPrice)->toBe(100.0);
    expect($detail->position->marketValue)->toBe(1000.0);
    expect($detail->position->gain)->toBe(200.0);
    expect($detail->position->gainPct)->toBe(25.0);
    expect($detail->transactions)->toHaveCount(1);
    expect($detail->sectors[0]->label)->toBe('Technologie');
});

it('omits the position when the instrument is not held', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $detail = app(GetInstrumentDetail::class)($user->id, $asset->id);

    expect($detail->position)->toBeNull();
});

it('omits the position when no price is available', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);

    $detail = app(GetInstrumentDetail::class)($user->id, $asset->id);

    expect($detail->position)->toBeNull();
});
