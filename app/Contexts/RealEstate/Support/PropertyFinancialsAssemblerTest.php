<?php

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

it('assembles the twelve month sliding windows', function () {
    Carbon::setTestNow('2026-08-20');

    $property = Property::factory()->create([
        'acquisition_price' => 100000,
        'acquisition_fees' => 10000,
    ]);
    Lease::factory()->create([
        'property_id' => $property->id,
        'monthly_rent' => 600,
        'start_date' => '2025-01-01',
        'end_date' => null,
    ]);
    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 90000,
        'annual_rate' => 0.0,
        'term_months' => 300,
        'start_date' => '2025-01-01',
        'monthly_insurance' => 0,
    ]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2026-03-10', 'amount' => 900]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2024-01-10', 'amount' => 500]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $financials = app(PropertyFinancialsAssembler::class)->financialsFor(
        $property->load(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::now(),
    );

    // 12 mois glissants = 2025-09 à 2026-08 : 12 loyers de 600, la charge 2024 est hors fenêtre.
    // Prêt à taux zéro : 90000/300 = 300 par mois, 12 × 300 = 3600.
    expect($financials->acquisitionPrice)->toBe(100000.0)
        ->and($financials->acquisitionFees)->toBe(10000.0)
        ->and($financials->currentMonthlyRent)->toBe(600.0)
        ->and($financials->rents12m)->toBe(7200.0)
        ->and($financials->expenses12m)->toBe(900.0)
        ->and($financials->loanPayments12m)->toBe(3600.0)
        ->and($financials->borrowedPrincipal)->toBe(90000.0)
        ->and($financials->currentValue)->toBe(120000.0)
        ->and($financials->remainingPrincipal)->toBeLessThan(90000.0);

    Carbon::setTestNow();
});
