<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;

/**
 * Chargement des trois pages de l'application : le squelette répond, les sections sont là dans
 * l'ordre attendu et rien n'explose côté JS. Les valeurs rendues dans ces sections sont vérifiées
 * par les tests dédiés à chaque composant (ValuationSectionTest, PerformanceBarsTest,
 * HeroSectionTest).
 */
it('charge le tableau de bord, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Investi')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'wealth-summary|wealth-evolution|wealth-income',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la fiche instrument et ses sections, sans erreur', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('ACME')
        ->assertSee('Transactions')
        ->assertSee('Répartition sectorielle')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|performance|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la fiche du bien et ses sections, sans erreur', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);

    Lease::factory()->ongoing()->create(['property_id' => $property->id, 'monthly_rent' => 600]);
    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 90000,
        'annual_rate' => 0.02,
        'term_months' => 240,
        'start_date' => now()->subYear()->toDateString(),
        'monthly_insurance' => 10,
    ]);
    PropertyExpense::factory()->create([
        'property_id' => $property->id,
        'date' => now()->subMonth()->toDateString(),
        'amount' => 150,
        'category' => ExpenseCategory::PropertyTax,
    ]);
    PropertyExpense::factory()->create([
        'property_id' => $property->id,
        'date' => now()->subMonths(3)->toDateString(),
        'amount' => 80,
        'category' => ExpenseCategory::Insurance,
    ]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => now()->toDateString(), 'value' => 150000]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertSee('T2 Lyon 7e')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|metrics|loan|income',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la page Titres, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|instruments|performances|income|sectors',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la page Immobilier et ses sections, sans erreur', function () {
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit('/properties')
        ->assertSee('Biens')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'real-estate-summary|real-estate-evolution|real-estate',
        )
        ->assertNoJavaScriptErrors();
});
