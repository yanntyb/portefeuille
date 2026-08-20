<?php

use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;

it('has valuations, leases, loans and expenses relations', function () {
    $property = Property::factory()->create();
    PropertyValuation::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create(['property_id' => $property->id]);
    RentException::factory()->create(['lease_id' => $lease->id]);
    Loan::factory()->create(['property_id' => $property->id]);
    PropertyExpense::factory()->create(['property_id' => $property->id]);

    expect($property->valuations)->toHaveCount(1)
        ->and($property->leases)->toHaveCount(1)
        ->and($property->leases->first()->exceptions)->toHaveCount(1)
        ->and($property->loans)->toHaveCount(1)
        ->and($property->expenses)->toHaveCount(1);
});

it('casts the expense category to an enum', function () {
    $expense = PropertyExpense::factory()->create(['category' => ExpenseCategory::PropertyTax]);

    expect($expense->refresh()->category)->toBe(ExpenseCategory::PropertyTax);
});

it('orders valuations by date so the last one is the current value', function () {
    $property = Property::factory()->create();
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-06-01', 'value' => 120000]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2025-01-01', 'value' => 100000]);

    expect((float) $property->valuations->last()->value)->toBe(120000.0);
});
