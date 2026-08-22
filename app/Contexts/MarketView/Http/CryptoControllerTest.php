<?php

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia;

it('ne montre que la crypto sur sa page', function () {
    $this->actingAs(cryptoFixture()['user']);

    $this->get(route('crypto.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Crypto/Index')
            ->has('overview.holdings', 1)
            ->where('overview.holdings.0.assetName', 'Bitcoin')
            ->where('overview.totalValue', fn (float|int $value): bool => (float) $value === 400.0));
});

it('laisse la crypto hors de la page Actions', function () {
    $this->actingAs(cryptoFixture()['user']);

    $this->get(route('instruments.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.holdings', 1)
            ->where('overview.holdings.0.assetName', 'ACME')
            ->where('overview.totalValue', fn (float|int $value): bool => (float) $value === 1000.0));
});

it('diffère les sections lourdes de la page crypto, sans secteurs ni revenus', function () {
    $this->actingAs(cryptoFixture()['user']);

    $response = $this->get(route('crypto.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ]);

    $response->assertOk();

    expect($response->json('deferredProps'))->toBe([
        'tendances' => ['trends'],
        'performances' => ['performances'],
        'evolution' => ['evolutionSeries'],
    ]);
});
