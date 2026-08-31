<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('explique les performances par période à travers un dialogue', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    // L'aide vit à côté du titre des performances, en bas de la section Analyse.
    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertSee('Performances')
        ->assertScript(
            "!!document.querySelector('[data-perf-help] [aria-label=\"Comment lire les performances par période\"]')",
            true,
        )
        ->click('[aria-label="Comment lire les performances par période"]')
        ->assertSee('Comment lire les performances par période')
        ->assertSee('cumulés, pas annualisés')
        ->assertSee('Apports')
        ->assertSee('les versements de la période')
        ->assertSee('la date de tes versements ne change rien')
        ->assertNoJavaScriptErrors();
});

it('déroule tous les secteurs sans bascule', function () {
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

    /** Les secteurs se lisent dans la section Analyse, sans pli à eux. */
    $labels = "document.querySelectorAll('[data-section=\"analysis\"] [data-sectors-block] [data-sector-label]').length";

    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertScript($labels, 8)
        ->assertScript("document.querySelectorAll('[data-sectors-block] [data-sector-toggle]').length", 0)
        ->assertSee('Énergie')
        ->assertSee('Immobilier')
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

    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertSee('Pas encore de données sectorielles.')
        ->assertNoJavaScriptErrors();
});

it('nomme la section des instruments pour les technologies d\'assistance', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertScript("document.querySelector('[data-section=instruments]').getAttribute('aria-label')", 'Instruments')
        ->assertNoJavaScriptErrors();
});

it('replie la liste des instruments derrière sa bascule', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $rows = "document.querySelectorAll('[data-instrument-row]').length";

    visit('/actions')
        ->assertScript($rows, 0)
        ->click('[data-section=instruments] [data-section-toggle]')
        ->assertScript("{$rows} >= 1", true)
        ->click('[data-section=instruments] [data-section-toggle]')
        ->assertScript($rows, 0)
        ->assertNoJavaScriptErrors();
});

it('garde les opérations de la poche repliées, et ne les charge qu\'au dépli', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertSeeIn('[data-section="class-transactions"]', 'Transactions')
        /** Repliée : la prop différée n'est même pas demandée tant que le pli tient. */
        ->assertMissing('[data-section="class-transactions"] [data-transaction-year]')
        ->click('[data-section="class-transactions"] [data-section-toggle]')
        ->assertSeeIn('[data-section="class-transactions"] [data-transaction-year="2026"]', '2026')
        ->click('[data-transaction-year="2026"]')
        ->assertSeeIn('[data-transaction-row] [data-transaction-asset]', 'ACME')
        ->assertNoJavaScriptErrors();
});
