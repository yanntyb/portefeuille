<?php

use App\Contexts\Wealth\Actions\GetWealthOverview;

it('additionne les classes d\'actif du registre', function () {
    fakeWealthClasses(
        fakeWealthClass('securities', value: 184200.0, invested: 160000.0),
        fakeWealthClass('realEstate', value: 128200.0, invested: 100000.0),
    );

    $overview = app(GetWealthOverview::class)(999);

    /**
     * Chaque nombre attendu est calculé à la main, pas relu sur l'objet produit : 184 200 + 128 200,
     * 160 000 + 100 000, et l'écart entre les deux totaux. Les classes gardent en plus leur propre
     * valeur pour qu'une permutation entre titres et immobilier fasse échouer le test.
     */
    expect($overview->totalValue)->toBe(312400.0)
        ->and($overview->totalInvested)->toBe(260000.0)
        ->and($overview->totalGain)->toBe(52400.0)
        ->and($overview->totalGainPct)->toBe(20.15)
        ->and($overview->classes[0]->key)->toBe('securities')
        ->and($overview->classes[0]->value)->toBe(184200.0)
        ->and($overview->classes[1]->key)->toBe('realEstate')
        ->and($overview->classes[1]->value)->toBe(128200.0);
});

it('rend les classes dans l\'ordre où elles ont été déclarées', function () {
    fakeWealthClasses(
        fakeWealthClass('realEstate', value: 1.0),
        fakeWealthClass('crypto', value: 2.0),
        fakeWealthClass('securities', value: 3.0),
    );

    expect(array_column(array_map(fn ($class) => $class->jsonSerialize(), app(GetWealthOverview::class)(999)->classes), 'key'))
        ->toBe(['realEstate', 'crypto', 'securities']);
});

it('rend un pourcentage de gain nul quand rien n\'a été investi', function () {
    fakeWealthClasses(fakeWealthClass('securities'));

    $overview = app(GetWealthOverview::class)(999);

    expect($overview->totalInvested)->toBe(0.0)
        ->and($overview->totalGainPct)->toBeNull();
});

it('porte le libellé et la page de chaque classe', function () {
    fakeWealthClasses(fakeWealthClass('securities', value: 1000.0, invested: 800.0));

    $line = app(GetWealthOverview::class)(999)->classes[0];

    expect($line->label)->toBe('Securities')
        ->and($line->href)->toBe('/securities')
        ->and($line->gainPct)->toBe(25.0);
});

it('rend un pourcentage de gain nul quand la mise d\'une classe est négative', function () {
    fakeWealthClasses(fakeWealthClass('realEstate', value: 50000.0, invested: -20000.0));

    $line = app(GetWealthOverview::class)(999)->classes[0];

    /** Un bien financé à plus de 100 % : le gain se calcule, le pourcentage n'a rien à quoi se rapporter. */
    expect($line->gainPct)->toBeNull()
        ->and($line->gain)->toBe(70000.0);
});
