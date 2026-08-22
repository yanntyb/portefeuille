<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use Inertia\Testing\AssertableInertia;

it('rend la fiche d\'une crypto', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $this->actingAs($user)
        ->get(route('crypto.show', $bitcoin->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Crypto/Show')
            ->where('instrument.name', 'Bitcoin'));
});

it('renvoie 404 quand l\'actif demandé n\'est pas une crypto', function () {
    ['user' => $user] = cryptoFixture();
    $stock = Instrument::query()->where('type', InstrumentType::Stock->value)->firstOrFail();

    $this->actingAs($user)
        ->get(route('crypto.show', $stock->id))
        ->assertNotFound();
});

it('renvoie 404 quand une crypto est demandée par la fiche des titres', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $this->actingAs($user)
        ->get(route('instruments.show', $bitcoin->id))
        ->assertNotFound();
});
