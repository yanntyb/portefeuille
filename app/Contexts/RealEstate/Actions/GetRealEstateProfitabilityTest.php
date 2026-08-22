<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetRealEstateProfitability;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

it('reports the profitability of each property of the user', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'name' => 'T2 Lyon 7e',
        'acquisition_date' => '2025-01-01',
        'acquisition_price' => 100000,
        'acquisition_fees' => 8000,
    ]);
    Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 600, 'start_date' => '2025-01-01', 'end_date' => null]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 90000, 'annual_rate' => 0.0, 'term_months' => 300, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $lines = app(GetRealEstateProfitability::class)($user->id);

    // Coût 108 000, loyers 7 200 sur douze mois, échéances 3 600, apport 18 000, restant dû 84 300.
    expect($lines)->toHaveCount(1)
        ->and($lines[0]->id)->toBe($property->id)
        ->and($lines[0]->name)->toBe('T2 Lyon 7e')
        ->and($lines[0]->metrics->grossYield)->toBe(0.0667)
        ->and($lines[0]->metrics->netYield)->toBe(0.0667)
        ->and($lines[0]->metrics->annualCashFlow)->toBe(3600.0)
        ->and($lines[0]->metrics->cashOnCash)->toBe(0.2)
        ->and($lines[0]->metrics->ltv)->toBe(0.7025);

    Carbon::setTestNow();
});

it('ignores the properties of other users', function () {
    $user = User::factory()->create();
    Property::factory()->create();

    expect(app(GetRealEstateProfitability::class)($user->id))->toBe([]);
});
