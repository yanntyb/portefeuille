<?php

use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

it('rend une série vide sans aucun bien', function () {
    $series = app(BuildRealEstateSeries::class)(999);

    expect($series->labels)->toBe([])
        ->and($series->netWorth)->toBe([])
        ->and($series->invested)->toBe([]);
});

it('termine sur aujourd\'hui et commence avant la plus ancienne acquisition', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $series = app(BuildRealEstateSeries::class)($user->id);

    expect($series->labels[count($series->labels) - 1])->toBe('2026-08-21')
        ->and($series->labels[0])->toBeLessThanOrEqual($property->acquisition_date->toDateString())
        ->and($series->netWorth)->toHaveCount(count($series->labels))
        ->and($series->invested)->toHaveCount(count($series->labels));
});

it('vaut zéro avant la date d\'acquisition', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $series = app(BuildRealEstateSeries::class)($user->id);

    expect($series->netWorth[0])->toBe(0.0)
        ->and($series->invested[0])->toBe(0.0);
});

it('lit la valeur estimée en escalier, sans interpoler', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture();
    $property->valuations()->delete();

    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => '2026-01-01',
        'value' => 100000,
    ]);
    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => '2026-07-01',
        'value' => 200000,
    ]);

    $series = app(BuildRealEstateSeries::class)($user->id);
    $byLabel = array_combine($series->labels, $series->netWorth);

    /** Sans prêt, la valeur nette vaut la valeur estimée. Avril tient la valeur de janvier. */
    $april = collect($series->labels)->first(fn (string $label): bool => str_starts_with($label, '2026-04'));

    expect($byLabel[$april])->toBe(100000.0);
});

it('fait décroître la valeur nette du capital remboursé', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $series = app(BuildRealEstateSeries::class)($user->id);
    $last = count($series->labels) - 1;

    /** Vingt mois d'échéances à taux nul : le restant dû a baissé, donc la valeur nette a monté. */
    expect($series->netWorth[$last])->toBeGreaterThan(0.0);
});
