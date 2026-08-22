<?php

use App\Contexts\Wealth\Actions\GetWealthIncome;

it('additionne ce que chaque classe verse chaque mois', function () {
    fakeWealthClasses(
        fakeWealthClass('securities', incomeLabel: 'Dividendes', monthlyIncome: 102.0),
        fakeWealthClass('realEstate', incomeLabel: 'Locatif net', monthlyIncome: 45.5),
    );

    $income = app(GetWealthIncome::class)(999);

    expect($income->monthlyTotal)->toBe(147.5)
        ->and($income->origins)->toHaveCount(2)
        ->and($income->origins[0]->label)->toBe('Dividendes')
        ->and($income->origins[0]->amount)->toBe(102.0)
        ->and($income->origins[1]->label)->toBe('Locatif net')
        ->and($income->origins[1]->amount)->toBe(45.5);
});

it('saute la classe qui ne verse rien plutôt que de lui donner une ligne à zéro', function () {
    fakeWealthClasses(
        fakeWealthClass('securities', incomeLabel: 'Dividendes', monthlyIncome: 60.0),
        fakeWealthClass('crypto'),
    );

    $income = app(GetWealthIncome::class)(999);

    expect($income->origins)->toHaveCount(1)
        ->and($income->origins[0]->label)->toBe('Dividendes')
        ->and($income->monthlyTotal)->toBe(60.0);
});

it('vaut zéro sans aucune classe', function () {
    fakeWealthClasses();

    $income = app(GetWealthIncome::class)(999);

    expect($income->monthlyTotal)->toBe(0.0)
        ->and($income->origins)->toBe([]);
});
