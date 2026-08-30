<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;

it('déroule l\'analyse d\'une exposition', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $other = Instrument::factory()->create(['name' => 'BETA', 'ticker' => 'BET']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => now(), 'close' => 200]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $other->id,
        'quantity' => 5,
        'avg_cost' => 150,
    ]);

    $this->actingAs($user);

    visit('/actions')
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->assertSee('Max drawdown')
        ->assertSee('Sous le plus-haut')
        ->assertScript("document.querySelectorAll('[data-correlation-cell]').length", 4)
        ->assertNoJavaScriptErrors();
});

it('dit qu\'il n\'y a rien à corréler sur une exposition vide', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/obligations')
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->assertSee('Pas encore de quoi analyser cette exposition.')
        ->assertNoJavaScriptErrors();
});
