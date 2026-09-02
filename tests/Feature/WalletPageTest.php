<?php

use App\Http\Middleware\HandleInertiaRequests;

it('sert la page de l\'enveloppe avec son en-tête', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $this->actingAs($user)
        ->get("/enveloppes/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Wallet/Show')
            ->where('account.walletId', $wallet->id)
            /** JSON ne distingue pas 1000 de 1000.0 : la même comparaison que le reste du dépôt. */
            ->where('account.marketValue', fn (float|int $value): bool => (float) $value === 1000.0)
        );
});

it('sert les positions, la ventilation, la série et le journal en props différées', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $response = $this->actingAs($user)->get("/enveloppes/{$wallet->id}");

    $deferred = $response->viewData('page')['deferredProps'];

    expect($deferred)->toHaveKeys(['positions', 'repartition', 'evolution', 'transactions'])
        ->and($deferred['positions'])->toBe(['positions'])
        ->and($deferred['repartition'])->toBe(['breakdown'])
        ->and($deferred['evolution'])->toBe(['evolution'])
        ->and($deferred['transactions'])->toBe(['transactions']);
});

it('résout les positions et le journal quand leur groupe est demandé', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user)
        ->get("/enveloppes/{$wallet->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Wallet/Show',
            'X-Inertia-Partial-Data' => 'positions,transactions',
        ])
        ->assertOk()
        ->assertJsonPath('props.positions.0.assetId', $instrument->id)
        ->assertJsonPath('props.transactions.0.walletId', $wallet->id);
});

it('répond 404 pour une enveloppe inconnue', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user)->get('/enveloppes/999999')->assertNotFound();
});

/** 404 et non 403 : le code ne doit pas révéler que l'enveloppe existe. */
it('répond 404 pour l\'enveloppe d\'un autre porteur', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    $this->actingAs($user)->get("/enveloppes/{$foreign->id}")->assertNotFound();
});

it('renvoie vers l\'accueil quand l\'identifiant n\'est pas numérique', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user)->get('/enveloppes/pea')->assertRedirect('/');
});
