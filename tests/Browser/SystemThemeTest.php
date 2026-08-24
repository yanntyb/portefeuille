<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

function seedSystemThemePortfolio(): User
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'unit_price' => 80,
        'date' => '2026-01-01',
    ]);

    return $user;
}

it('applique le thème sombre quand le système le préfère', function () {
    $this->actingAs(seedSystemThemePortfolio());

    visit('/')->inDarkMode()
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

it('abandonne le thème sombre quand le système préfère un thème clair', function () {
    $this->actingAs(seedSystemThemePortfolio());

    visit('/')->inLightMode()
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertScript('getComputedStyle(document.body).backgroundColor', 'rgb(255, 255, 255)')
        ->assertNoJavaScriptErrors();
});

it('peint le fil d\'Ariane collant sur le fond du thème plutôt qu\'un noir figé', function () {
    $user = seedSystemThemePortfolio();
    $instrument = Instrument::query()->firstOrFail();
    $this->actingAs($user);

    visit("/asset/{$instrument->id}")->inLightMode()
        ->assertScript(
            "(() => {
                const probe = document.createElement('div');
                probe.style.backgroundColor = 'color-mix(in oklab, var(--background) 95%, transparent)';
                document.body.appendChild(probe);
                const expected = getComputedStyle(probe).backgroundColor;
                probe.remove();

                return getComputedStyle(document.querySelector('footer')).backgroundColor === expected
                    ? 'theme background'
                    : 'hardcoded';
            })()",
            'theme background',
        )
        ->assertNoJavaScriptErrors();
});
