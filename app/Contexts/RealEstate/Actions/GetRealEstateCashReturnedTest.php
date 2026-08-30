<?php

use App\Contexts\RealEstate\Actions\GetRealEstateCashReturned;
use Illuminate\Support\Carbon;

it('somme les mois que le bien a rendus', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    /**
     * Vingt et un mois depuis l'acquisition (décembre 2024). Janvier 2026 est déficitaire et ne
     * rend rien ; le mois d'acquisition rend 600 € — la première échéance ne tombe qu'au mois
     * suivant — et les dix-neuf autres 266,67 €, soit 5 666,73 €.
     */
    expect(app(GetRealEstateCashReturned::class)($user->id))->toBe(5666.73);
});

it('vaut zéro pour un bien qui n\'a jamais couvert ses charges', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);
    $property->leases()->delete();

    expect(app(GetRealEstateCashReturned::class)($user->id))->toBe(0.0);
});

it('vaut zéro sans aucun bien', function () {
    expect(app(GetRealEstateCashReturned::class)(999))->toBe(0.0);
});
