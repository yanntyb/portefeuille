<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function instrumentWithValuation(): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF']);

    foreach (['2026-01-15' => 90, '2026-03-15' => 110, '2026-07-01' => 100] as $date => $close) {
        Price::factory()->create(['asset_id' => $asset->id, 'date' => $date, 'close' => $close]);
    }

    foreach (['2026-01-15', '2026-03-15'] as $date) {
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

    return ['user' => $user, 'instrument' => $asset];
}

it('offers the periods alone, without a granularity switch', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=valuation] button')).map(el => el.textContent.trim()).join('|')",
            '1M|6M|1A|Max',
        )
        ->assertNoJavaScriptErrors();
});

it('plots the position value against what was invested, in euros', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=valuation] .apexcharts-series')).map(el => el.getAttribute('seriesName')).join('|')",
            'Valeur|Investi',
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=valuation] .apexcharts-yaxis-label')).every(el => el.textContent.includes('€'))",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('leaves the chart legend out, the lines speak for themselves', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "document.querySelectorAll('[data-section=valuation] .apexcharts-legend-text').length",
            0,
        )
        ->assertNoJavaScriptErrors();
});
