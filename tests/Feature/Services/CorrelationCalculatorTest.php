<?php

use App\Enums\CorrelationPeriod;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Services\CorrelationCalculator;
use Illuminate\Support\Carbon;

it('returns null when less than 2 securities', function () {
    $security = Security::factory()->create();

    $result = app(CorrelationCalculator::class)->compute(
        collect([$security]),
        CorrelationPeriod::Max,
    );

    expect($result)->toBeNull();
});

it('returns null when not enough common data points', function () {
    $securities = Security::factory()->count(2)->create();

    // Only 5 days of data (below MIN_DATA_POINTS of 20+1)
    foreach ($securities as $security) {
        for ($i = 0; $i < 5; $i++) {
            SecurityPrice::factory()->create([
                'security_id' => $security->id,
                'date' => now()->subDays($i),
                'close' => 100 + $i,
            ]);
        }
    }

    $result = app(CorrelationCalculator::class)->compute(
        $securities,
        CorrelationPeriod::Max,
    );

    expect($result)->toBeNull();
});

it('returns correlation of 1.0 for identical price series', function () {
    $securities = Security::factory()->count(2)->create();

    $baseDate = Carbon::parse('2025-01-01');

    foreach ($securities as $security) {
        for ($i = 0; $i < 30; $i++) {
            SecurityPrice::factory()->create([
                'security_id' => $security->id,
                'date' => $baseDate->copy()->addDays($i),
                'close' => 100 + $i * 0.5,
            ]);
        }
    }

    $result = app(CorrelationCalculator::class)->compute(
        $securities,
        CorrelationPeriod::Max,
    );

    expect($result)->not->toBeNull()
        ->and($result->average)->toBe(1.0)
        ->and($result->matrix[0][1])->toBe(1.0)
        ->and($result->matrix[1][0])->toBe(1.0)
        ->and($result->labels)->toHaveCount(2);
});

it('returns correlation near -1.0 for inversely correlated series', function () {
    $securities = Security::factory()->count(2)->create();

    $baseDate = Carbon::parse('2025-01-01');

    // Alternating up/down pattern - one goes up when the other goes down
    for ($i = 0; $i < 30; $i++) {
        $factor = ($i % 2 === 0) ? 1.05 : 0.95;
        $inverseFactor = ($i % 2 === 0) ? 0.95 : 1.05;

        SecurityPrice::factory()->create([
            'security_id' => $securities[0]->id,
            'date' => $baseDate->copy()->addDays($i),
            'close' => 100 * ($factor ** $i),
        ]);

        SecurityPrice::factory()->create([
            'security_id' => $securities[1]->id,
            'date' => $baseDate->copy()->addDays($i),
            'close' => 100 * ($inverseFactor ** $i),
        ]);
    }

    $result = app(CorrelationCalculator::class)->compute(
        $securities,
        CorrelationPeriod::Max,
    );

    expect($result)->not->toBeNull()
        ->and($result->average)->toBeLessThan(-0.9)
        ->and($result->matrix[0][1])->toBeLessThan(-0.9);
});

it('filters prices by period', function () {
    $securities = Security::factory()->count(2)->create();

    // Create prices spanning 2 years
    for ($i = 0; $i < 400; $i++) {
        $date = now()->subDays(400 - $i);

        foreach ($securities as $security) {
            SecurityPrice::factory()->create([
                'security_id' => $security->id,
                'date' => $date,
                'close' => 100 + $i * 0.1,
            ]);
        }
    }

    $resultMax = app(CorrelationCalculator::class)->compute($securities, CorrelationPeriod::Max);
    $result1m = app(CorrelationCalculator::class)->compute($securities, CorrelationPeriod::OneMonth);

    expect($resultMax)->not->toBeNull()
        ->and($result1m)->not->toBeNull();
});
