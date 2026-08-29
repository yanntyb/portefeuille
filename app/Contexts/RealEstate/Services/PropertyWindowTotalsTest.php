<?php

use App\Contexts\RealEstate\Services\PropertyWindowTotals;

it('somme les montants de la fenêtre, bornes comprises', function () {
    expect((new PropertyWindowTotals)->within([
        ['month' => '2026-01-01', 'amount' => 100.0],
        ['month' => '2026-06-01', 'amount' => 50.0],
        ['month' => '2026-09-01', 'amount' => 25.0],
    ], '2026-01-01', '2026-06-30'))->toBe(150.0);
});

it('exclut ce qui précède la fenêtre', function () {
    expect((new PropertyWindowTotals)->within([
        ['month' => '2025-12-31', 'amount' => 100.0],
    ], '2026-01-01', '2026-06-30'))->toBe(0.0);
});

it('rend zéro sans aucun montant', function () {
    expect((new PropertyWindowTotals)->within([], '2026-01-01', '2026-06-30'))->toBe(0.0);
});
