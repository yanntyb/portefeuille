<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('explique les performances par période à travers un dialogue', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions/analyse')
        ->assertSee('Performances')
        ->click('[aria-label="Comment lire les performances par période"]')
        ->assertSee('Comment lire les performances par période')
        ->assertSee('cumulés, pas annualisés')
        ->assertSee('Apports')
        ->assertSee('les versements de la période')
        ->assertSee('la date de tes versements ne change rien')
        ->assertNoJavaScriptErrors();
});

it('replie les secteurs au-delà du sixième derrière une bascule', function () {
    // Un utilisateur nu : portfolioFixture() créerait une position sans SectorAllocation, dont le
    // poids non alloué tombe dans un secteur « Autre » — neuf secteurs au lieu de huit.
    $user = User::factory()->create();

    holdingWithSectors($user, 'ACME ETF', 1000.0, [
        Sector::Technology->value => 0.3,
        Sector::Healthcare->value => 0.2,
        Sector::FinancialServices->value => 0.15,
        Sector::CommunicationServices->value => 0.12,
        Sector::ConsumerCyclical->value => 0.1,
        Sector::Industrials->value => 0.07,
        Sector::Energy->value => 0.04,
        Sector::RealEstate->value => 0.02,
    ]);

    $this->actingAs($user);

    $labels = "document.querySelectorAll('[data-section=\"sectors\"] [data-sector-label]').length";

    visit('/actions/analyse')
        ->assertScript($labels, 6)
        ->assertDontSee('Énergie')
        ->click('[data-sector-toggle]')
        ->assertScript($labels, 8)
        ->assertSee('Énergie')
        ->assertSee('Immobilier')
        ->click('[data-sector-toggle]')
        ->assertScript($labels, 6)
        ->assertNoJavaScriptErrors();
});

it('affiche un état vide quand aucune position n\'a de valeur de marché', function () {
    // Aucun cours pour l'instrument : la position n'a pas de valeur de marché, la section
    // sectorielle n'a donc rien à répartir.
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'ACME ETF']);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => 100,
    ]);

    $this->actingAs($user);

    visit('/actions/analyse')
        ->assertSee('Pas encore de données sectorielles.')
        ->assertNoJavaScriptErrors();
});

it('nomme la section des instruments pour les technologies d\'assistance', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertScript("document.querySelector('[data-section=instruments]').getAttribute('aria-label')", 'Instruments')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length >= 1", true)
        ->assertNoJavaScriptErrors();
});
