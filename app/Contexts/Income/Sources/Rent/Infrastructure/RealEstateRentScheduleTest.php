<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Rent\Infrastructure\RealEstateRentSchedule;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Support\Carbon;

it('yields one receipt per collected month, skipping vacancy and full defaults', function () {
    Carbon::setTestNow('2026-04-15');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    $lease = Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 500, 'start_date' => '2026-01-01', 'end_date' => null]);
    RentException::factory()->create(['lease_id' => $lease->id, 'month' => '2026-02-01', 'amount_override' => 0]);
    RentException::factory()->create(['lease_id' => $lease->id, 'month' => '2026-03-01', 'amount_override' => 250]);

    $receipts = app(RealEstateRentSchedule::class)->receiptsFor($user->id);

    expect(array_map(fn ($r): array => [$r->month, $r->amount, $r->propertyName], $receipts))->toBe([
        ['2026-01-01', 500.0, 'T2 Lyon 7e'],
        ['2026-03-01', 250.0, 'T2 Lyon 7e'],
        ['2026-04-01', 500.0, 'T2 Lyon 7e'],
    ]);

    Carbon::setTestNow();
});

it('projects the yearly rent of active leases', function () {
    Carbon::setTestNow('2026-04-15');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 500, 'start_date' => '2026-01-01', 'end_date' => null]);

    expect(app(RealEstateRentSchedule::class)->projectedAnnualFor($user->id))->toBe(6000.0);

    Carbon::setTestNow();
});
