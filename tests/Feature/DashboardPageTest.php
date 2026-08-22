<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\GetWealthIncome;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

it('rend un patrimoine vide sans aucune donnée', function () {
    User::factory()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn (float $value): bool => $value === 0.0)
            ->where('overview.totalGainPct', null)
        );
});

it('additionne les titres et l\'immobilier dans le grand chiffre', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('overview.securities')
            ->has('overview.realEstate')
            ->where('overview.totalValue', fn (float $total): bool => $total > 0.0)
        );
});

it('diffère la série du patrimoine', function () {
    Carbon::setTestNow('2026-08-21');
    propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('series')
        );
});

it('diffère le revenu mensuel et n\'y compte les loyers qu\'une fois', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('income'));

    /** Le groupe `revenus` n'arrive qu'à la requête partielle qui le réclame. */
    $this->get('/', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'income',
    ])
        ->assertOk()
        ->assertJsonStructure(['props' => ['income' => ['monthlyTotal', 'monthlyDividends', 'monthlyRentalNet']]]);

    $income = app(GetWealthIncome::class)($user->id);

    /**
     * `Income` agrège aussi `IncomeSource::Rent`, brut. Le total du patrimoine additionne les
     * dividendes filtrés et le locatif **net** : il ne peut donc pas atteindre le loyer brut.
     */
    expect($income->monthlyTotal)->toBe(round($income->monthlyDividends + $income->monthlyRentalNet, 2))
        ->and($income->monthlyRentalNet)->toBeLessThan(600.0);
});
