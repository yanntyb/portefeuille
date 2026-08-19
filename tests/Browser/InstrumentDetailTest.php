<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Dividend;
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

it('aligne le graphe de valorisation sur la marge du reste de la page, y compris sur mobile', function () {
    // Le graphe débordait de la marge : rendu bord à bord sur mobile, il commençait avant les
    // titres et finissait après eux. Les deux écarts sont mesurés, pas seulement celui de gauche.
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $paddingGaps = "(() => {
        const chart = document.querySelector('[data-section=\"valuation\"] [data-chart]').getBoundingClientRect();
        const heading = document.querySelector('[data-section=\"performance\"] h2').getBoundingClientRect();

        return [Math.round(chart.left - heading.left), Math.round(chart.right - heading.right)].join('|');
    })()";

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Performance par période')
        ->assertScript($paddingGaps, '0|0')
        ->assertNoJavaScriptErrors();
});

it('affiche les dividendes perçus quand l\'instrument en verse', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.5,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Dividendes (1)')
        ->assertScript("document.querySelectorAll('[data-dividend-row]').length", 1)
        // `includes` et non une égalité : `Intl` sépare le montant du symbole par une espace
        // insécable étroite, invisible dans le source du test mais fatale à une comparaison stricte.
        ->assertScript("document.querySelector('[data-dividend-total]').textContent.includes('5,00')", true)
        ->assertNoJavaScriptErrors();
});

it('n\'affiche aucune section dividendes sur un instrument capitalisant', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertDontSee('Dividendes')
        ->assertScript("document.querySelectorAll('[data-section=\"dividends\"]').length", 0)
        ->assertNoJavaScriptErrors();
});
