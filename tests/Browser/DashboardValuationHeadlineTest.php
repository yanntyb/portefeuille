<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

function userWithValuedPortfolio(): User
{
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    return $user;
}

it('leads the dashboard with the portfolio value and its variation', function () {
    $this->actingAs(userWithValuedPortfolio());

    visit('/')
        ->assertSee('Valeur du portefeuille')
        ->assertScript(
            "document.querySelector('[data-portfolio-value]').textContent.replace(/\\s/g, ' ').trim()",
            '1 000 €',
        )
        ->assertNoJavaScriptErrors();
});

it('reads the invested amount and the gain next to the value', function () {
    $this->actingAs(userWithValuedPortfolio());

    visit('/')
        ->assertScript(
            "document.querySelector('[data-portfolio-meta]').textContent.replace(/\\s+/g, ' ').trim()",
            'Investi 800 € Gain +200 €',
        )
        ->assertNoJavaScriptErrors();
});
