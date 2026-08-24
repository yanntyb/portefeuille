<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Http\Middleware\HandleInertiaRequests;

it('serves a list page for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetClass/Index')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel())
            ->where('assetClass.hasSectors', $class->hasSectors())
            ->where('assetClass.hasIncome', $class === AssetClass::Equity));
})->with(AssetClass::cases());

/**
 * `sectorBreakdown` et `income` voyagent en props différées : absentes de `props` à la première
 * réponse, elles n'apparaissent que dans `deferredProps`, comme le vérifie déjà
 * `CryptoControllerTest` pour `tendances`/`performances`/`evolution`.
 */
it('offers sectors and income on equity alone', function () {
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];

    $equity = $this->get(route('classes.equity'), $headers);
    $equity->assertOk();
    expect($equity->json('deferredProps'))->toHaveKeys(['secteurs', 'revenus']);

    $crypto = $this->get(route('classes.crypto'), $headers);
    $crypto->assertOk();
    expect($crypto->json('deferredProps'))
        ->not->toHaveKey('secteurs')
        ->not->toHaveKey('revenus');
});
