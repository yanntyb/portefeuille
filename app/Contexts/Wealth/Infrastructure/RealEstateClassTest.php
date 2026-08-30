<?php

use App\Contexts\RealEstate\Actions\GetRealEstateCashInvested;
use App\Contexts\RealEstate\Actions\GetRealEstateCashReturned;
use App\Contexts\Wealth\Infrastructure\RealEstateClass;
use Illuminate\Support\Carbon;

it('porte en gain réalisé le cash que les loyers ont rendu', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $snapshot = app(RealEstateClass::class)->snapshotFor($user->id);

    /**
     * Le réalisé de l'immobilier est le miroir de sa mise : les mois déficitaires gonflent
     * l'investi, les excédentaires remontent en gain. Sans lui, le loyer encaissé disparaissait du
     * patrimoine et deux biens de même rendement affichaient des gains différents.
     */
    expect($snapshot->realized)
        ->toBe(app(GetRealEstateCashReturned::class)($user->id))
        ->toBeGreaterThan(0.0);
});

it('ne compte aucun mois à la fois en mise et en gain réalisé', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $snapshot = app(RealEstateClass::class)->snapshotFor($user->id);

    expect($snapshot->invested)->toBe(app(GetRealEstateCashInvested::class)($user->id));
});

it('rend un gain réalisé nul pour un utilisateur sans bien', function () {
    expect(app(RealEstateClass::class)->snapshotFor(999)->realized)->toBe(0.0);
});
