<?php

use App\Contexts\Portfolio\Services\CostBasis;

it('moyenne deux achats à des prix différents', function () {
    expect((new CostBasis)->of([
        ['quantity' => 10.0, 'unitPrice' => 80.0],
        ['quantity' => 10.0, 'unitPrice' => 100.0],
    ]))->toBe(['quantity' => 20.0, 'cost' => 1800.0, 'average' => 90.0]);
});

it('compte les frais d\'achat dans le prix de revient', function () {
    expect((new CostBasis)->of([
        ['quantity' => 10.0, 'unitPrice' => 80.0, 'fees' => 5.0],
        ['quantity' => 10.0, 'unitPrice' => 100.0, 'fees' => 15.0],
    ]))->toBe(['quantity' => 20.0, 'cost' => 1820.0, 'average' => 91.0]);
});

it('rend une moyenne de zéro sans aucun achat', function () {
    expect((new CostBasis)->of([]))->toBe(['quantity' => 0.0, 'cost' => 0.0, 'average' => 0.0]);
});
