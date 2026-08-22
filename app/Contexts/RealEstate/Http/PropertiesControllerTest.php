<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia;

/**
 * En-têtes d'une requête Inertia. La version vient du middleware et non d'`Inertia::getVersion()` :
 * celle-ci n'est renseignée qu'une fois le middleware passé, donc vide au moment du test.
 *
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function inertiaHeaders(array $extra = []): array
{
    return array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ], $extra);
}

/** Un bien loué, estimé : de quoi remplir chacune des sections de la page. */
function propertiesPageUser(): User
{
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    Lease::factory()->ongoing()->create(['property_id' => $property->id, 'monthly_rent' => 600]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'value' => 120000]);

    return $user;
}

it('renders the portfolio totals without waiting for the heavy sections', function () {
    $this->actingAs(propertiesPageUser());

    $this->get(route('properties.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Properties/Index')
            ->where('realEstate.properties.0.name', 'T2 Lyon 7e')
            ->has('realEstate.totalInvested')
            ->has('realEstate.totalMonthlyCashFlow')
            ->missing('series')
            ->missing('profitability')
            ->missing('income'));
});

it('serves each heavy section in its own deferred group', function () {
    $this->actingAs(propertiesPageUser());

    $response = $this->get(route('properties.index'), inertiaHeaders());

    $response->assertOk();

    // Un groupe par section : Inertia en résout un par requête, donc chaque squelette se remplit
    // à son rythme au lieu d'attendre le plus lent de la page.
    expect($response->json('deferredProps'))->toBe([
        'evolution' => ['series'],
        'rentabilité' => ['profitability'],
        'revenus' => ['income'],
    ]);
});

it('resolves a deferred section on a partial reload', function () {
    $this->actingAs(propertiesPageUser());

    $response = $this->get(route('properties.index'), inertiaHeaders([
        'X-Inertia-Partial-Component' => 'Properties/Index',
        'X-Inertia-Partial-Data' => 'profitability',
    ]));

    $response->assertOk();

    expect($response->json('props.profitability.0.name'))->toBe('T2 Lyon 7e')
        ->and($response->json('props.profitability.0.metrics.netYield'))->toBeFloat();
});
