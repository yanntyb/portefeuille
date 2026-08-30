<?php

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;

it('rend la page analyse d\'une exposition', function () {
    cryptoFixture();

    $this->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
            ->where('assetClass.key', 'equity')
            ->where('assetClass.label', 'Actions')
            ->where('assetClass.slug', 'actions')
        );
});

it('sert une page analyse par exposition', function () {
    cryptoFixture();

    foreach (['/actions/analyse', '/crypto/analyse', '/obligations/analyse', '/matieres-premieres/analyse'] as $url) {
        $this->get($url)->assertOk();
    }
});

/**
 * `assertInertia()` s'appuie sur `assertViewHas('page')`, qui suppose un rendu Blade complet :
 * une requête partielle (en-tête `X-Inertia`) ne produit que du JSON, sans vue. C'est pourquoi
 * `AssetClassControllerTest` et `AssetControllerTest` lisent une prop différée via `->json(...)`
 * plutôt que via `assertInertia()` — on reprend la même façon de faire ici.
 */
it('résout les analyses en prop différée', function () {
    cryptoFixture();

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'AssetClass/Analysis',
        'X-Inertia-Partial-Data' => 'analysis',
    ];

    $response = $this->get('/actions/analyse', $headers);

    $response->assertOk();
    expect($response->json('props.analysis.concentration.top1'))->toEqual(100.0);
});
