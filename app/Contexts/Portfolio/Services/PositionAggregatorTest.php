<?php

use App\Contexts\Portfolio\Services\PositionAggregator;

it('somme les quantités de deux enveloppes', function () {
    expect((new PositionAggregator)([
        ['quantity' => 10.0, 'avgCost' => 80.0],
        ['quantity' => 4.0, 'avgCost' => 95.0],
    ]))->toBe(['quantity' => 14.0, 'avgCost' => (10.0 * 80.0 + 4.0 * 95.0) / 14.0]);
});

it('ignore les enveloppes sans prix de revient dans la moyenne', function () {
    expect((new PositionAggregator)([
        ['quantity' => 10.0, 'avgCost' => 80.0],
        ['quantity' => 5.0, 'avgCost' => null],
    ]))->toBe(['quantity' => 15.0, 'avgCost' => 80.0]);
});

it('rend un prix de revient nul quand aucune enveloppe n\'en a', function () {
    expect((new PositionAggregator)([
        ['quantity' => 10.0, 'avgCost' => null],
    ]))->toBe(['quantity' => 10.0, 'avgCost' => null]);
});
