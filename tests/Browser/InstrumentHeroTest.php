<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function instrumentHero(bool $held, bool $withHistory = false): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF', 'ticker' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    if ($held) {
        $wallet = Wallet::factory()->for($user)->create();
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_cost' => 80,
        ]);

        if ($withHistory) {
            foreach (['2026-01-15', '2026-03-15'] as $date) {
                Price::factory()->create(['asset_id' => $asset->id, 'date' => $date, 'close' => 90]);
                Transaction::factory()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'asset_id' => $asset->id,
                    'date' => $date,
                    'quantity' => 5,
                    'unit_price' => 80,
                    'fees' => 0,
                ]);
            }
        }
    }

    return ['user' => $user, 'instrument' => $asset];
}

it('leads the held instrument sheet with its value, gain and cost basis', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentHero(held: true);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelector('[data-hero-value]').textContent.replace(/\\s/g, ' ').trim()", '1 000,00 €')
        ->assertScript("document.querySelector('[data-hero-gain]').textContent.replace(/\\s+/g, ' ').trim()", '+200,00 € (+25,0 %)')
        ->assertScript(
            "document.querySelector('[data-hero-meta]').textContent.replace(/\\s+/g, ' ').trim()",
            '10 titres · PRU 80,00 € · investi 800,00 € · cours 100,00 €',
        )
        ->assertNoJavaScriptErrors();
});

it('leads the unheld instrument sheet with its last price', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentHero(held: false);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelector('[data-hero-value]').textContent.replace(/\\s/g, ' ').trim()", '100,00 €')
        ->assertScript("document.querySelector('[data-hero-meta]').textContent.replace(/\\s+/g, ' ').trim()", 'au 01/07/2026')
        ->assertScript("document.querySelectorAll('[data-hero-gain]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('draws a sparkline next to the hero figure', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentHero(held: true, withHistory: true);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('[data-hero-spark] svg').length", 1)
        ->assertNoJavaScriptErrors();
});
