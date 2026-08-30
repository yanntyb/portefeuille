<?php

use App\Contexts\RealEstate\Services\CashFlowCalculator;
use Illuminate\Support\Carbon;

it('rend un mois par mois de la fenêtre, du plus ancien au plus récent', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);

    $flows = app(CashFlowCalculator::class)->months(
        $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-08-21'),
    );

    expect($flows)->toHaveCount(3)
        ->and($flows[0]->month)->toBe('2026-06-01')
        ->and($flows[2]->month)->toBe('2026-08-01');
});

it('compose le net d\'un mois de son loyer, de ses charges et de son échéance', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);

    $flows = app(CashFlowCalculator::class)->months(
        $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::parse('2026-07-01'),
        Carbon::parse('2026-07-31'),
    );

    /** 80 000 € à taux nul sur 240 mois : 333,33 € d'échéance. Loyer 600 €, aucune charge en juillet. */
    expect($flows[0]->rents)->toBe(600.0)
        ->and($flows[0]->expenses)->toBe(0.0)
        ->and($flows[0]->loanPayment)->toBe(333.33)
        ->and($flows[0]->net)->toBe(266.67);
});

it('rend un net négatif le mois où les charges dépassent le loyer', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);

    $flows = app(CashFlowCalculator::class)->months(
        $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
    );

    /** Janvier porte les deux charges du fixture : 750 € de travaux et 250 € de taxe foncière. */
    expect($flows[0]->expenses)->toBe(1000.0)
        ->and($flows[0]->net)->toBe(-733.33);
});

it('ne retient en surplus que les mois excédentaires, symétriques des injections', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);
    $property = $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']);

    $surpluses = app(CashFlowCalculator::class)->surplusesSince($property, Carbon::parse('2026-08-21'));
    $injections = app(CashFlowCalculator::class)->injectionsSince($property, Carbon::parse('2026-08-21'));

    /** Janvier 2026 est le seul mois déficitaire du fixture : il injecte, il ne rend rien. */
    expect($surpluses)->not->toHaveKey('2026-01-01')
        ->and($injections)->toHaveKey('2026-01-01')
        ->and($surpluses['2026-07-01'])->toBe(266.67)
        ->and(array_intersect_key($surpluses, $injections))->toBe([]);
});
