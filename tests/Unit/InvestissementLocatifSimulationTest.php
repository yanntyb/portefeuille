<?php

use App\Data\Simulation\CdiVsSasuSimulation;
use App\Data\Simulation\InvestissementLocatifSimulation;
use App\Data\Simulation\SimulationObject;
use App\Services\SimulationEngine;

function simulationEngine(): SimulationEngine
{
    return new SimulationEngine;
}

it('builds the simulation with correct structure', function (): void {
    $simulation = InvestissementLocatifSimulation::build();

    expect($simulation->nom)->toBe('Investissement Locatif')
        ->and($simulation->pipelineNames)->toBe(['Bien 1', 'Bien 2'])
        ->and($simulation->objects)->not->toBeEmpty()
        ->and($simulation->scenarios)->not->toBeEmpty();

    $pipelineObjects = collect($simulation->objects)
        ->filter(fn (SimulationObject $o): bool => ! empty($o->pipeline));

    $bien1Count = $pipelineObjects->filter(fn (SimulationObject $o): bool => $o->pipeline === 'Bien 1')->count();
    $bien2Count = $pipelineObjects->filter(fn (SimulationObject $o): bool => $o->pipeline === 'Bien 2')->count();

    expect($bien1Count)->toBe($bien2Count);
});

it('computes mensualite_credit correctly', function (): void {
    $engine = simulationEngine();

    // 150 000 €, 3.5% annuel, 240 mois
    $result = $engine->mensualiteCredit(150_000, [
        'taux_interet_annuel' => 0.035,
        'duree_credit_mois' => 240,
    ]);

    expect($result)->not->toBeNull();

    // M = 150000 * (0.035/12 / (1 - (1 + 0.035/12)^(-240)))
    $r = 0.035 / 12;
    $expected = 150_000 * ($r / (1 - pow(1 + $r, -240)));
    expect(round($result, 2))->toBe(round($expected, 2));
    // ~869.88
    expect(round($result, 0))->toBe(870.0);
});

it('computes mensualite_credit with zero rate', function (): void {
    $engine = simulationEngine();

    $result = $engine->mensualiteCredit(120_000, [
        'taux_interet_annuel' => 0.0,
        'duree_credit_mois' => 240,
    ]);

    expect($result)->toBe(500.0);
});

it('computes all objects without errors', function (): void {
    $simulation = InvestissementLocatifSimulation::build();
    $engine = simulationEngine();

    $computed = $engine->computeObjects($simulation->objects);

    $values = collect($computed)->filter(fn (SimulationObject $o): bool => ! empty($o->steps))->keyBy('nom');

    // Rendement brut bien 1 : 7800 / 64500 ≈ 12.09%
    $rendementBrut = $values['rendement_brut_bien1']->value->numeric;
    expect($rendementBrut)->not->toBeNull();
    expect(round($rendementBrut * 100, 1))->toBeBetween(10.0, 14.0);

    // Cash flow mensuel
    $cashFlow = $values['cash_flow_mensuel_bien1']->value->numeric;
    expect($cashFlow)->not->toBeNull();

    // Rendement net net should be positive
    $rendementNetNet = $values['rendement_net_net_bien1']->value->numeric;
    expect($rendementNetNet)->not->toBeNull();
    expect($rendementNetNet)->toBeGreaterThan(0);
});

it('computes all scenarios without errors', function (): void {
    $simulation = InvestissementLocatifSimulation::build();
    $engine = simulationEngine();

    $results = $engine->computeAllScenarios($simulation->objects, $simulation->scenarios);

    expect($results)->toHaveCount(count($simulation->scenarios));

    foreach ($results as $result) {
        expect($result->scenario)->not->toBeEmpty();
        expect($result->results)->not->toBeEmpty();
    }
});

it('does not break the CDI vs SASU simulation', function (): void {
    $simulation = CdiVsSasuSimulation::build();
    $engine = simulationEngine();

    $computed = $engine->computeObjects($simulation->objects);
    $values = collect($computed)->keyBy('nom');

    $cdiRem = $values['cdi_remuneration']->value->numeric;
    expect($cdiRem)->not->toBeNull();
    expect($cdiRem)->toBeGreaterThan(0);

    $sasuRem = $values['sasu_remuneration_totale']->value->numeric;
    expect($sasuRem)->not->toBeNull();
    expect($sasuRem)->toBeGreaterThan(0);

    $results = $engine->computeAllScenarios($simulation->objects, $simulation->scenarios);
    expect($results)->toHaveCount(count($simulation->scenarios));
});
