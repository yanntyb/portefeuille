<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\BuildPropertyValueSeries;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

it('yields one point a month from the acquisition month to the current one', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'acquisition_date' => '2026-05-14',
        'acquisition_price' => 100000,
    ]);

    $series = app(BuildPropertyValueSeries::class)($user->id, $property->id);

    expect($series->labels)->toBe(['2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01']);
});

it('holds the last known valuation until the next one', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'acquisition_date' => '2026-05-01',
        'acquisition_price' => 100000,
    ]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-06-10', 'value' => 120000]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-08-01', 'value' => 130000]);

    $series = app(BuildPropertyValueSeries::class)($user->id, $property->id);

    expect($series->values)->toBe([100000.0, 120000.0, 120000.0, 130000.0]);
});

it('tracks the remaining principal month by month, and zero without a loan', function () {
    Carbon::setTestNow('2026-04-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'acquisition_date' => '2026-01-01',
        'acquisition_price' => 100000,
    ]);
    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 1200,
        'annual_rate' => 0.0,
        'term_months' => 12,
        'start_date' => '2026-01-01',
        'monthly_insurance' => 0,
    ]);

    $withLoan = app(BuildPropertyValueSeries::class)($user->id, $property->id);

    $noLoan = Property::factory()->create(['user_id' => $user->id, 'acquisition_date' => '2026-03-01']);

    expect($withLoan->remaining)->toBe([1200.0, 1100.0, 1000.0, 900.0])
        ->and(app(BuildPropertyValueSeries::class)($user->id, $noLoan->id)->remaining)->toBe([0.0, 0.0]);
});

it('yields an empty series for an unknown or foreign property', function () {
    $user = User::factory()->create();
    $foreign = Property::factory()->create();

    expect(app(BuildPropertyValueSeries::class)($user->id, $foreign->id)->labels)->toBe([])
        ->and(app(BuildPropertyValueSeries::class)($user->id, 404)->labels)->toBe([]);
});
