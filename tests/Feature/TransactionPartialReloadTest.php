<?php

use App\Contexts\Portfolio\Models\Transaction;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Testing\TestResponse;

/**
 * Le filet du chemin d'écriture : après une saisie, le client redemande la page en **partiel**
 * (`only`), et c'est ce qui rend la redirection acceptable là où `StartSyncController` la refuse.
 *
 * Deux choses à prouver, et la seconde est la règle n°1 de `.ai/rules/pwa.md` : une réponse
 * partielle ne porte pas `deferredProps`, faute de quoi le client relancerait tous les groupes
 * différés de la page — et, hors-ligne, le service worker boucherait sur `loadDeferredProps`.
 */
function partialVisit(string $uri, string $component, string $only): TestResponse
{
    /**
     * La version vient du middleware et non d'une constante : une valeur inventée rendrait 409, et
     * le test croirait avoir prouvé quelque chose sur une réponse de conflit.
     */
    $version = (new HandleInertiaRequests)->version(request());

    return test()->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $only,
    ])->get($uri);
}

it('rends exactly the props asked for, and no deferred metadata', function () {
    portfolioFixture();

    $response = partialVisit('/', 'Dashboard', 'overview,transactions');

    $response->assertOk()
        ->assertJsonPath('component', 'Dashboard')
        ->assertJsonMissingPath('deferredProps')
        ->assertJsonStructure(['props' => ['overview', 'transactions']]);

    /** Les props non demandées ne sont pas renvoyées : le serveur ne recalcule que le demandé. */
    expect(array_keys($response->json('props')))
        ->toEqualCanonicalizing(['errors', 'overview', 'transactions']);
});

it('resolves a deferred group when the partial names it', function () {
    portfolioFixture();

    /**
     * `transactions` est différée sur le tableau de bord. Nommée dans un partiel, elle est bel et
     * bien calculée — c'est ce qui permet de rafraîchir une section dépliée sans recharger la page.
     */
    $lines = partialVisit('/', 'Dashboard', 'transactions')->json('props.transactions');

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['id'])->toBe(Transaction::query()->sole()->id);
});

it('rends the fresh line right after a write, in one round trip', function () {
    ['wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id))->assertRedirect();

    $props = partialVisit('/', 'Dashboard', 'overview,transactions')->json('props');

    expect($props['transactions'])->toHaveCount(2);
});

it('keeps the same guarantee on an exposure page and on an asset page', function () {
    ['instrument' => $instrument] = portfolioFixture();

    partialVisit('/actions', 'AssetClass/Index', 'overview,transactions')
        ->assertOk()
        ->assertJsonMissingPath('deferredProps');

    /**
     * Sur la fiche, l'historique n'est pas une prop : il est imbriqué dans `instrument`, qui est
     * synchrone. Un seul `only` rafraîchit donc la liste et la carte de position.
     */
    $instrumentProp = partialVisit("/asset/{$instrument->id}", 'Asset/Show', 'instrument')
        ->assertJsonMissingPath('deferredProps')
        ->json('props.instrument');

    expect($instrumentProp['transactions'])->toHaveCount(1)
        ->and($instrumentProp['transactions'][0])->toHaveKeys(['id', 'walletId']);
});
