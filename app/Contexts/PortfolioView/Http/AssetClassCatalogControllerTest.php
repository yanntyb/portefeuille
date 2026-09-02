<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use Inertia\Testing\AssertableInertia as Assert;

it('sert un catalogue pour chaque exposition', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}.catalog"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Catalog')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel())
            ->where('assetClass.slug', $class->slug())
            ->has('catalog'));
})->with(AssetClass::cases());

it('liste un instrument de la classe que personne ne détient', function () {
    Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'ACM']);

    $this->get('/actions/catalogue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Catalog')
            ->has('catalog', 1)
            ->where('catalog.0.name', 'ACME')
            ->where('catalog.0.typeLabel', InstrumentType::Stock->getLabel())
            ->where('catalog.0.held', false));
});

it('écarte du catalogue les instruments d\'une autre exposition', function () {
    Instrument::factory()->create(['name' => 'ACME']);
    Instrument::factory()->ofType(InstrumentType::Commodity)->create(['name' => 'Or']);

    $this->get('/matieres-premieres/catalogue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('catalog', 1)
            ->where('catalog.0.name', 'Or'));
});

it('marque et valorise la position de l\'utilisateur connecté', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user)
        ->get('/actions/catalogue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('catalog.0.id', $instrument->id)
            ->where('catalog.0.held', true)
            ->where('catalog.0.quantity', 10)
            ->where('catalog.0.marketValue', 1000));
});

/** Aucun utilisateur en base : `AuthenticateDefaultUser` n'a personne à connecter, et le
 *  catalogue reste lisible — il ne parle du portefeuille que par sa marque « détenu ». */
it('sert le catalogue quand aucun utilisateur n\'existe', function () {
    Instrument::factory()->create(['name' => 'ACME']);

    $this->get('/actions/catalogue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('catalog', 1)
            ->where('catalog.0.held', false));
});

it('sert un catalogue vide quand la classe ne porte aucun instrument', function () {
    $this->get('/crypto/catalogue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('catalog', 0));
});
