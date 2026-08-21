<?php

use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
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

it('fait décroître la valeur nette du capital remboursé, à valeur estimée constante', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);
    $property->valuations()->delete();

    /** Valeur estimée constante à 150 000 € posée à l'acquisition et aujourd'hui : sous l'escalier,
     *  chaque label entre les deux lit la même valeur. Toute variation de la valeur nette ne peut
     *  donc venir que du capital remboursé, jamais de la valuation. */
    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => $property->acquisition_date->toDateString(),
        'value' => 150000,
    ]);
    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => now()->toDateString(),
        'value' => 150000,
    ]);

    $series = app(BuildRealEstateSeries::class)($user->id);
    $byLabel = array_combine($series->labels, $series->netWorth);

    $earlier = collect($series->labels)->first(fn (string $label): bool => str_starts_with($label, '2025-10'));
    $last = $series->labels[count($series->labels) - 1];

    expect($byLabel[$last])->toBeGreaterThan($byLabel[$earlier]);

    $loan = $property->loans->first();
    $schedule = app(PropertyFinancialsAssembler::class)->scheduleFor($loan);
    $remainingAtLast = app(LoanAmortizationCalculator::class)->remainingAt($schedule, Carbon::parse($last));

    /** Recalculé depuis les mêmes services que le code : un flip de signe ou une soustraction
     *  manquante ferait échouer cette assertion, contrairement à un simple `> 0`. */
    expect($byLabel[$last])->toBe(round(150000.0 - $remainingAtLast, 2));
});

it('ne duplique pas le label quand aujourd\'hui est un lundi', function () {
    Carbon::setTestNow('2026-08-24 15:32:10');
    ['user' => $user] = propertyFixture();

    $series = app(BuildRealEstateSeries::class)($user->id);

    /** 2026-08-24 est un lundi : sans la comparaison en jour, il apparaîtrait deux fois en fin de
     *  série (une fois via la boucle, une fois via le dernier point forcé). */
    expect($series->labels)->toHaveCount(count(array_unique($series->labels)))
        ->and(array_slice($series->labels, -1))->toBe(['2026-08-24']);
});
