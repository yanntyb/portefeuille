<?php

use App\Contexts\RealEstate\Services\ExpenseGrouper;

it('groupe par année décroissante et par catégorie décroissante', function () {
    $years = (new ExpenseGrouper)->byYear([
        ['year' => 2025, 'category' => 'works', 'label' => 'Travaux', 'amount' => 300.0],
        ['year' => 2026, 'category' => 'works', 'label' => 'Travaux', 'amount' => 250.0],
        ['year' => 2026, 'category' => 'property_tax', 'label' => 'Taxe foncière', 'amount' => 750.0],
    ]);

    expect(array_column($years, 'year'))->toBe([2026, 2025])
        ->and($years[0]['total'])->toBe(1000.0)
        ->and(array_column($years[0]['byCategory'], 'category'))->toBe(['property_tax', 'works']);
});

it('additionne deux charges de même catégorie et même année', function () {
    $years = (new ExpenseGrouper)->byYear([
        ['year' => 2026, 'category' => 'works', 'label' => 'Travaux', 'amount' => 250.0],
        ['year' => 2026, 'category' => 'works', 'label' => 'Travaux', 'amount' => 100.0],
    ]);

    expect($years[0]['byCategory'])->toBe([
        ['category' => 'works', 'label' => 'Travaux', 'amount' => 350.0],
    ]);
});

it('rend une liste vide sans charge', function () {
    expect((new ExpenseGrouper)->byYear([]))->toBe([]);
});
