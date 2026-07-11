<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('links a holding to its asset, wallet and user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);

    $holding = Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    expect((float) $holding->quantity)->toBe(10.0)
        ->and($holding->asset->name)->toBe('ACME')
        ->and($holding->wallet->is($wallet))->toBeTrue()
        ->and($holding->user->is($user))->toBeTrue();
});
