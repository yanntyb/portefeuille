<?php

use App\Domains\Analytics\Services\MonteCarloEngine;

it('returns valid monte carlo result', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 1000,
        versementMensuel: 100,
        tauxMoyen: 0.07,
        volatilite: 0.15,
        duree: 10,
        nbSimulations: 500,
    );

    expect($result)->toBeObject()
        ->and($result->p10)->toBeArray()
        ->and($result->p50)->toBeArray()
        ->and($result->p90)->toBeArray()
        ->and($result->capitalInvesti)->toBeArray();
});

it('maintains percentile ordering p10 <= p50 <= p90', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 1000,
        versementMensuel: 100,
        tauxMoyen: 0.07,
        volatilite: 0.15,
        duree: 10,
        nbSimulations: 500,
    );

    for ($t = 0; $t <= 10; $t++) {
        expect($result->p10[$t])->toBeLessThanOrEqual($result->p50[$t])
            ->and($result->p50[$t])->toBeLessThanOrEqual($result->p90[$t]);
    }
});

it('calculates capital invested correctly', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 10000,
        versementMensuel: 500,
        tauxMoyen: 0.07,
        volatilite: 0.15,
        duree: 5,
        nbSimulations: 100,
    );

    expect($result->capitalInvesti[0])->toBe(10000.0);
    expect($result->capitalInvesti[1])->toBe(10000.0 + 500 * 12);
    expect($result->capitalInvesti[2])->toBe(10000.0 + 500 * 12 * 2);
    expect($result->capitalInvesti[5])->toBe(10000.0 + 500 * 12 * 5);
});

it('returns non-negative portfolio values', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 1000,
        versementMensuel: 100,
        tauxMoyen: 0.07,
        volatilite: 0.5, // high volatility
        duree: 10,
        nbSimulations: 500,
    );

    for ($t = 0; $t <= 10; $t++) {
        expect($result->p10[$t])->toBeGreaterThanOrEqual(0.0)
            ->and($result->p50[$t])->toBeGreaterThanOrEqual(0.0)
            ->and($result->p90[$t])->toBeGreaterThanOrEqual(0.0);
    }
});

it('p90 generally exceeds p50 which exceeds p10', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 1000,
        versementMensuel: 100,
        tauxMoyen: 0.07,
        volatilite: 0.15,
        duree: 10,
        nbSimulations: 1000,
    );

    $p10_less_than_p50 = 0;
    $p50_less_than_p90 = 0;

    for ($t = 1; $t <= 10; $t++) {
        if ($result->p10[$t] < $result->p50[$t]) {
            $p10_less_than_p50++;
        }
        if ($result->p50[$t] < $result->p90[$t]) {
            $p50_less_than_p90++;
        }
    }

    // At least 80% of years should follow this pattern
    expect($p10_less_than_p50)->toBeGreaterThan(8)
        ->and($p50_less_than_p90)->toBeGreaterThan(8);
});

it('portfolio grows over time with positive returns', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 1000,
        versementMensuel: 100,
        tauxMoyen: 0.07,
        volatilite: 0.10,
        duree: 10,
        nbSimulations: 500,
    );

    // p50 should generally increase over time
    $p50_increasing = 0;
    for ($t = 1; $t <= 10; $t++) {
        if ($result->p50[$t] > $result->p50[$t - 1]) {
            $p50_increasing++;
        }
    }

    expect($p50_increasing)->toBeGreaterThan(7);
});

it('handles zero monthly deposit', function () {
    $engine = new MonteCarloEngine;
    $result = $engine->compute(
        capitalInitial: 1000,
        versementMensuel: 0,
        tauxMoyen: 0.07,
        volatilite: 0.15,
        duree: 5,
        nbSimulations: 100,
    );

    expect($result->capitalInvesti[0])->toBe(1000.0);
    expect($result->capitalInvesti[1])->toBe(1000.0);
    expect($result->capitalInvesti[5])->toBe(1000.0);
});
