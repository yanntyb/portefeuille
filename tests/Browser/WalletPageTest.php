<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * La page d'une enveloppe affiche les mêmes sections qu'une page d'exposition : ces deux tests
 * l'exercent à l'écran, sur les deux nouveautés — les repères de l'en-tête et l'analyse.
 */
it('porte l\'investi et le gain de l\'enveloppe sous son grand chiffre', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $this->actingAs($user);

    visit("/enveloppes/{$wallet->id}")
        ->assertSee('Investi')
        ->assertScript("document.querySelector('[data-wallet-meta] [data-gain]').textContent.includes('latent')", true)
        ->assertNoJavaScriptErrors();
});

it('déroule l\'analyse de l\'enveloppe, sans mêler l\'enveloppe voisine', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $beta = Instrument::factory()->create(['name' => 'BETA', 'ticker' => 'BET']);
    Price::factory()->create(['asset_id' => $beta->id, 'date' => now(), 'close' => 200]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $beta->id,
        'quantity' => 5, 'avg_cost' => 150,
    ]);

    /** Le titre du compte voisin ne doit apparaître ni dans la matrice ni dans les positions. */
    $neighborWallet = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $neighbor = Instrument::factory()->create(['name' => 'VOISIN', 'ticker' => 'VOI']);
    Price::factory()->create(['asset_id' => $neighbor->id, 'date' => now(), 'close' => 50]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $neighborWallet->id, 'asset_id' => $neighbor->id,
        'quantity' => 10, 'avg_cost' => 50,
    ]);

    $this->actingAs($user);

    visit("/enveloppes/{$wallet->id}")
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->assertSee('Max drawdown')
        ->assertSee('Sous le plus-haut')
        ->assertScript("document.querySelectorAll('[data-correlation-cell]').length", 4)
        ->assertDontSee('VOI')
        ->assertNoJavaScriptErrors();
});
