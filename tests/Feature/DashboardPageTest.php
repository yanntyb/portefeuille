<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
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
            ->has('overview.classes', 5)
            ->where('overview.classes.0.key', 'equity')
            ->where('overview.classes.1.key', 'bond')
            ->where('overview.classes.2.key', 'commodity')
            ->where('overview.classes.3.key', 'crypto')
            ->where('overview.classes.4.key', 'realEstate')
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
        ->assertJsonStructure(['props' => ['income' => ['monthlyTotal', 'origins' => [['label', 'amount']]]]]);

    $income = app(GetWealthIncome::class)($user->id);
    $byLabel = collect($income->origins)->keyBy('label');

    /**
     * `Income` agrège aussi `IncomeSource::Rent`, brut. Le total du patrimoine additionne les
     * dividendes filtrés et le locatif **net** : il ne peut donc pas atteindre le loyer brut.
     */
    expect($income->monthlyTotal)->toBe(round(collect($income->origins)->sum('amount'), 2))
        ->and($byLabel['Locatif net']->amount)->toBeLessThan(600.0);
});

it('compte la crypto comme sa propre classe, séparée des titres', function () {
    ['user' => $user] = portfolioFixture();

    $wallet = Wallet::factory()->for($user)->create();
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 400]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $bitcoin->id,
        'quantity' => 1, 'avg_cost' => 300,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.classes', 5)
            ->where('overview.classes.3.key', 'crypto')
            ->where('overview.classes.3.label', 'Crypto')
            ->where('overview.classes.3.href', '/crypto')
            ->where('overview.classes.3.value', fn (float|int $value): bool => (float) $value === 400.0)
            /** La position de 10 titres à 100 € vaut 1 000 € : la crypto n'y est plus comptée. */
            ->where('overview.classes.0.value', fn (float|int $value): bool => (float) $value === 1000.0)
            ->where('overview.totalValue', fn (float|int $value): bool => (float) $value === 1400.0)
        );
});
