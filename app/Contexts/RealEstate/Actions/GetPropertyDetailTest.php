<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetPropertyDetail;
use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Support\Carbon;

it('assembles the whole property sheet', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'name' => 'T2 Lyon 7e',
        'acquisition_price' => 100000,
        'acquisition_fees' => 10000,
    ]);
    $lease = Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 600, 'start_date' => '2025-01-01', 'end_date' => null]);
    RentException::factory()->create(['lease_id' => $lease->id, 'month' => '2026-02-01', 'amount_override' => 0, 'note' => 'Impayé']);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 90000, 'annual_rate' => 0.0, 'term_months' => 300, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2026-03-10', 'amount' => 900, 'category' => ExpenseCategory::PropertyTax]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $detail = app(GetPropertyDetail::class)($user->id, $property->id);

    expect($detail->name)->toBe('T2 Lyon 7e')
        ->and($detail->currentValue)->toBe(120000.0)
        ->and($detail->monthlyCashFlows)->toHaveCount(12)
        ->and($detail->rentHistory[0]->month)->toBe('2026-08-01')
        ->and($detail->loan->monthlyPayment)->toBe(300.0)
        ->and($detail->expenseYears[0]->year)->toBe(2026)
        ->and($detail->expenseYears[0]->byCategory['property_tax'])->toBe(900.0)
        ->and($detail->metrics->grossYield)->toBe(0.0655);

    Carbon::setTestNow();
});

it('yields null for a property of another user', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();

    expect(app(GetPropertyDetail::class)($user->id, $property->id))->toBeNull();
});
