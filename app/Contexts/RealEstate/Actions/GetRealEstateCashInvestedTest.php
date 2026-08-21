<?php

use App\Contexts\RealEstate\Actions\GetRealEstateCashInvested;
use App\Contexts\RealEstate\Models\Loan;
use Illuminate\Support\Carbon;

it('compte l\'apport et les mois que le bien n\'a pas couverts', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    /**
     * Apport = 100 000 + 8 000 − 80 000 = 28 000 €.
     * Un seul mois déficitaire dans la fenêtre du fixture : janvier 2026, où 1 000 € de charges
     * s'ajoutent à 333,33 € d'échéance contre 600 € de loyer, soit 733,33 € injectés.
     */
    expect(app(GetRealEstateCashInvested::class)($user->id))->toBe(28733.33);
});

it('ne compte pas comme sorti le capital remboursé par le locataire', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $investi = app(GetRealEstateCashInvested::class)($user->id);

    /**
     * Vingt mois d'échéances à 333,33 € valent 6 666,60 € de capital remboursé. S'il était compté
     * comme une mise, l'investi dépasserait 34 000 €. Il ne doit pas : ce capital est sorti du
     * loyer, pas de la poche.
     */
    expect($investi)->toBeLessThan(30000.0);
});

it('conserve un apport négatif quand l\'emprunt dépasse le coût d\'acquisition', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture();

    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 120000,
        'annual_rate' => 0.0,
        'term_months' => 240,
        'start_date' => $property->acquisition_date->toDateString(),
        'monthly_insurance' => 0,
    ]);

    /** Apport = 100 000 + 8 000 − 120 000 = −12 000 €, conservé tel quel. */
    expect(app(GetRealEstateCashInvested::class)($user->id))->toBeLessThan(0.0);
});

it('vaut le coût d\'acquisition pour un bien détenu sans prêt et sans charge', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture();
    $property->expenses()->delete();

    expect(app(GetRealEstateCashInvested::class)($user->id))->toBe(108000.0);
});

it('vaut zéro sans aucun bien', function () {
    expect(app(GetRealEstateCashInvested::class)(999))->toBe(0.0);
});
