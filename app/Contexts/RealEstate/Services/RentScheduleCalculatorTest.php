<?php

use App\Contexts\RealEstate\Datas\LeaseTermData;
use App\Contexts\RealEstate\Datas\RentExceptionData;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->calculator = new RentScheduleCalculator;
});

it('yields one line per month from the first lease, vacancy included', function () {
    $leases = [
        new LeaseTermData(start: '2026-01-01', end: '2026-03-31', monthlyRent: 500.0),
        new LeaseTermData(start: '2026-05-01', end: null, monthlyRent: 600.0),
    ];
    $exceptions = [new RentExceptionData(month: '2026-02-01', amountOverride: 250.0)];

    $months = $this->calculator->months($leases, $exceptions, Carbon::parse('2026-06-15'));

    expect(array_map(fn ($m): array => [$m->month, $m->expected, $m->effective], $months))->toBe([
        ['2026-01-01', 500.0, 500.0],
        ['2026-02-01', 500.0, 250.0],
        ['2026-03-01', 500.0, 500.0],
        ['2026-04-01', 0.0, 0.0],
        ['2026-05-01', 600.0, 600.0],
        ['2026-06-01', 600.0, 600.0],
    ]);
});

it('yields nothing without any lease', function () {
    expect($this->calculator->months([], [], Carbon::parse('2026-06-15')))->toBe([]);
});

it('treats a lease ending mid-month as covering that month', function () {
    $leases = [new LeaseTermData(start: '2026-01-01', end: '2026-02-10', monthlyRent: 500.0)];

    $months = $this->calculator->months($leases, [], Carbon::parse('2026-02-20'));

    expect($months[1]->expected)->toBe(500.0);
});

it('projects the active lease over twelve months', function () {
    $leases = [new LeaseTermData(start: '2026-01-01', end: null, monthlyRent: 600.0)];

    expect($this->calculator->projectedAnnual($leases, Carbon::parse('2026-06-15')))->toBe(7200.0);
});

it('projects zero without an active lease', function () {
    $leases = [new LeaseTermData(start: '2026-01-01', end: '2026-03-31', monthlyRent: 600.0)];

    expect($this->calculator->projectedAnnual($leases, Carbon::parse('2026-06-15')))->toBe(0.0);
});
