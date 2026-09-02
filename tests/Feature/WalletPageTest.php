<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
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

it('sert chaque section en prop différée, un groupe par section', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $response = $this->actingAs($user)->get("/enveloppes/{$wallet->id}");

    $deferred = $response->viewData('page')['deferredProps'];

    /** Un groupe par section, comme sur une page d'exposition : chaque squelette à son rythme. */
    expect($deferred)->toHaveKeys(['positions', 'repartition', 'evolution', 'performances', 'analyse', 'secteurs', 'transactions'])
        ->and($deferred['positions'])->toBe(['positions'])
        ->and($deferred['repartition'])->toBe(['breakdown'])
        ->and($deferred['evolution'])->toBe(['evolution'])
        ->and($deferred['performances'])->toBe(['performances'])
        ->and($deferred['analyse'])->toBe(['basketAnalysis'])
        ->and($deferred['secteurs'])->toBe(['sectorBreakdown'])
        ->and($deferred['transactions'])->toBe(['transactions']);
});

/**
 * L'analyse et les performances de l'enveloppe passent par les mêmes ports qu'une exposition, au
 * périmètre près : ce test épingle qu'elles sont bien scopées au compte, et non calculées sur le
 * portefeuille entier — l'enveloppe voisine tient un titre que la matrice ne doit pas nommer.
 */
it('sert une analyse et des performances scopées à l\'enveloppe', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $neighbor = Instrument::factory()->create(['name' => 'Voisin', 'ticker' => 'VOI']);
    Price::factory()->create(['asset_id' => $neighbor->id, 'date' => now(), 'close' => 50]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $other->id, 'asset_id' => $neighbor->id,
        'quantity' => 10, 'avg_cost' => 50,
    ]);

    $response = $this->actingAs($user)
        ->get("/enveloppes/{$wallet->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Wallet/Show',
            'X-Inertia-Partial-Data' => 'basketAnalysis,performances,breakdown',
        ])
        ->assertOk();

    $tickers = array_column($response->json('props.basketAnalysis.instruments'), 'label');

    expect($tickers)->toBe([$instrument->ticker])
        ->and($response->json('props.performances'))->not->toBeNull()
        ->and(array_column($response->json('props.breakdown'), 'key'))->toBe(['equity']);
});

it('résout les positions, la série et le journal quand leur groupe est demandé', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user)
        ->get("/enveloppes/{$wallet->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Wallet/Show',
            'X-Inertia-Partial-Data' => 'positions,evolution,transactions',
        ])
        ->assertOk()
        ->assertJsonPath('props.positions.0.assetId', $instrument->id)
        /**
         * Seul groupe non exercé ailleurs dans ce fichier : sans lui, une inversion d'arguments
         * sur le chemin de la série (`ValuationPort::evolutionFor()`) ne serait détectée par aucun
         * test bout en bout. Une fermeture et non un index fixe : l'ordre des labels n'est pas ce
         * qui est affirmé ici, seulement que le titre détenu vaut bien 1000 € en dernier point.
         */
        ->assertJsonPath('props.evolution.perAsset.0.value', fn (array $value): bool => (float) end($value) === 1000.0)
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
