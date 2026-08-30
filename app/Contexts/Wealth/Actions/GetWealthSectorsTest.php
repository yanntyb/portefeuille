<?php

use App\Contexts\Wealth\Actions\GetWealthSectors;

it('additionne les secteurs de toutes les classes et les pèse contre le patrimoine', function () {
    fakeWealthClasses(
        fakeWealthClass('equity', sectors: ['Technologie' => 600.0, 'Santé' => 200.0]),
        fakeWealthClass('realEstate', sectors: ['Immobilier' => 200.0]),
    );

    $sectors = app(GetWealthSectors::class)(999);

    expect($sectors)->toHaveCount(3)
        ->and($sectors[0]->label)->toBe('Technologie')
        ->and($sectors[0]->value)->toBe(600.0)
        ->and($sectors[0]->pct)->toBe(60.0)
        ->and($sectors[1]->label)->toBe('Santé')
        ->and($sectors[1]->pct)->toBe(20.0)
        ->and($sectors[2]->label)->toBe('Immobilier')
        ->and($sectors[2]->pct)->toBe(20.0);
});

it('fond un même secteur porté par deux classes en une seule tranche', function () {
    // Un ETF actions et un ETF obligataire exposés tous deux à la finance : deux classes, un secteur.
    fakeWealthClasses(
        fakeWealthClass('equity', sectors: ['Finance' => 300.0]),
        fakeWealthClass('bond', sectors: ['Finance' => 100.0]),
    );

    $sectors = app(GetWealthSectors::class)(999);

    expect($sectors)->toHaveCount(1)
        ->and($sectors[0]->value)->toBe(400.0)
        ->and($sectors[0]->pct)->toBe(100.0);
});

it('trie les tranches par poids décroissant, quel que soit l\'ordre du registre', function () {
    fakeWealthClasses(
        fakeWealthClass('equity', sectors: ['Santé' => 100.0]),
        fakeWealthClass('realEstate', sectors: ['Immobilier' => 900.0]),
    );

    expect(array_map(fn ($slice): string => $slice->label, app(GetWealthSectors::class)(999)))
        ->toBe(['Immobilier', 'Santé']);
});

it('ne rend rien quand aucune classe ne vaut quoi que ce soit', function () {
    fakeWealthClasses(fakeWealthClass('equity'), fakeWealthClass('realEstate'));

    expect(app(GetWealthSectors::class)(999))->toBe([]);
});

it('écarte une tranche sans valeur plutôt que de lui donner une part nulle', function () {
    fakeWealthClasses(fakeWealthClass('equity', sectors: ['Technologie' => 500.0, 'Énergie' => 0.0]));

    $sectors = app(GetWealthSectors::class)(999);

    expect($sectors)->toHaveCount(1)
        ->and($sectors[0]->label)->toBe('Technologie');
});
