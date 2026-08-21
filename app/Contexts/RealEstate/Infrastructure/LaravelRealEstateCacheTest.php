<?php

use App\Contexts\RealEstate\Infrastructure\LaravelRealEstateCache;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
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
    /**
     * Midi, et non minuit : le test du lendemain ne franchit le jour qu'après douze heures, bien
     * en deçà des vingt-quatre heures de `LaravelRealEstateCache::TTL_SECONDS`. À minuit pile, le
     * saut d'un jour coïnciderait avec l'expiration du TTL et masquerait une empreinte qui aurait
     * oublié la date du jour.
     */
    Carbon::setTestNow('2026-08-21 12:00:00');
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

it('recalcule dès qu\'un bien change de prix d\'acquisition', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    $this->property->update(['acquisition_price' => 120000]);

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule dès qu\'un second prêt est ajouté', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    Loan::factory()->create(['property_id' => $this->property->id]);

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule dès qu\'un loyer change', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    Lease::query()->where('property_id', $this->property->id)->first()->update(['monthly_rent' => 650]);

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule dès qu\'une charge est ajoutée', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    PropertyExpense::factory()->create(['property_id' => $this->property->id]);

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

/**
 * Seul chemin de l'empreinte qui ne peut pas filtrer par `property_id` directement : les
 * exceptions se rattachent à un bail, pas à un bien, d'où la double jointure `lease_id IN
 * (baux IN (biens de l'utilisateur))`.
 */
it('recalcule dès qu\'une exception de loyer est ajoutée', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    $lease = Lease::query()->where('property_id', $this->property->id)->first();
    RentException::factory()->create(['lease_id' => $lease->id]);

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
