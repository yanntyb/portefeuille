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
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
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

it('applies the dark theme when the system prefers it', function () {
    $this->actingAs(seedSystemThemePortfolio());

    visit('/')->inDarkMode()
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors();
});

it('drops the dark theme when the system prefers a light one', function () {
    $this->actingAs(seedSystemThemePortfolio());

    visit('/')->inLightMode()
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertScript('getComputedStyle(document.body).backgroundColor', 'oklch(1 0 0)')
        ->assertNoJavaScriptErrors();
});

it('paints the sticky breadcrumb on the theme background rather than a hardcoded black', function () {
    $user = seedSystemThemePortfolio();
    $instrument = Instrument::query()->firstOrFail();
    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")->inLightMode()
        ->assertScript("getComputedStyle(document.querySelector('header')).backgroundColor", 'oklab(1 0 0 / 0.95)')
        ->assertNoJavaScriptErrors();
});

it('darkens the evolution areas so they stay visible on a light background', function () {
    $this->actingAs(seedSystemThemePortfolio());

    $areaFills = "Array.from(document.querySelectorAll('[data-section=evolution] path'))"
        .'.map(path => path.getAttribute("fill")).join(",")';

    visit('/')->inLightMode()
        ->assertScript("{$areaFills}.includes('#334155')", true)
        ->assertNoJavaScriptErrors();

    visit('/')->inDarkMode()
        ->assertScript("{$areaFills}.includes('#e2e8f0')", true)
        ->assertNoJavaScriptErrors();
});
