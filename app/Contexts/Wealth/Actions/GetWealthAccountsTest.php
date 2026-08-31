<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Actions\GetWealthAccounts;

it('rend les enveloppes de l\'utilisateur, la plus grosse en tête', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    foreach ([[$pea, 100.0, 10.0], [$cto, 50.0, 2.0]] as [$wallet, $close, $qty]) {
        $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
        Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => $qty,
            'avg_cost' => $close,
        ]);
    }

    $accounts = app(GetWealthAccounts::class)($user->id);

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]->walletName)->toBe('PEA')
        ->and($accounts[1]->walletName)->toBe('CTO');
});
