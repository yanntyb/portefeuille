<?php

use App\Contexts\RealEstate\Infrastructure\LaravelRealEstateCache;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

/**
 * Une instance par appel : l'empreinte n'est mémoïsée que le temps d'une requête, et c'est bien
 * d'une requête à la suivante que l'invalidation doit se voir.
 */
function rememberRealEstate(int $userId, int &$calls): string
{
    return (new LaravelRealEstateCache)->remember('serie', $userId, function () use (&$calls): string {
        $calls++;

        return 'calculé';
    });
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-21');
    ['user' => $this->user, 'property' => $this->property] = propertyFixture(['loan' => true]);
});

it('ne recalcule pas tant que rien ne bouge', function () {
    $calls = 0;

    expect(rememberRealEstate($this->user->id, $calls))->toBe('calculé');
    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(1);
});

it('recalcule après une suppression en masse par le query builder', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    /** Exactement ce que fait RealEstateDemoSeeder::purgeRelated() : aucun événement de modèle. */
    PropertyValuation::query()->where('property_id', $this->property->id)->delete();

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule dès qu\'une valeur estimée change', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    PropertyValuation::factory()->create([
        'property_id' => $this->property->id,
        'date' => '2026-08-01',
        'value' => 175000,
    ]);

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('ne resserve pas la série de la veille', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    Carbon::setTestNow('2026-08-22');

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('ne mélange pas les séries de deux utilisateurs', function () {
    $calls = 0;
    ['user' => $other] = propertyFixture();

    rememberRealEstate($this->user->id, $calls);
    rememberRealEstate($other->id, $calls);

    expect($calls)->toBe(2);
});
