<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('déplie les transactions derrière leur compte, sans déranger l\'ordre des sections', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertSee('Transactions (1)')
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|performance|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});

it('détaille le montant de chaque secteur quand l\'instrument est détenu', function () {
    // Deux secteurs de poids distincts (60/40) sur une valeur de marché de 1 000 € : deux
    // montants différents l'un de l'autre et de la valeur totale, pour discriminer un mutant qui
    // afficherait la valeur brute ou intervertirait les poids.
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Technology, 'weight' => 0.6]);
    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Healthcare, 'weight' => 0.4]);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-sector-amount]')).map(el => el.textContent.replace(/\\s/g, ' ')).join('|')",
            '600 €|400 €',
        )
        ->assertNoJavaScriptErrors();
});

it('affiche uniquement la part sectorielle quand l\'instrument n\'est pas détenu', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Technology, 'weight' => 0.6]);
    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Healthcare, 'weight' => 0.4]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-sector-amount]').length", 0)
        ->assertScript("document.querySelectorAll('[data-sector-share]').length", 2)
        ->assertNoJavaScriptErrors();
});
