<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

it('sums net worth over the user properties', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 600, 'start_date' => '2025-01-01', 'end_date' => null]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 90000, 'annual_rate' => 0.0, 'term_months' => 300, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $overview = app(GetRealEstateOverview::class)($user->id);

    // 19 échéances passées (2025-02 à 2026-08) × 300 = 5700 remboursés, restant 84300.
    expect($overview->properties)->toHaveCount(1)
        ->and($overview->properties[0]->name)->toBe('T2 Lyon 7e')
        ->and($overview->properties[0]->currentValue)->toBe(120000.0)
        ->and($overview->properties[0]->remainingPrincipal)->toBe(84300.0)
        ->and($overview->properties[0]->netWorth)->toBe(35700.0)
        ->and($overview->totalNetWorth)->toBe(35700.0);

    Carbon::setTestNow();
});

it('ignores properties of other users and yields an empty overview', function () {
    $user = User::factory()->create();
    Property::factory()->create();

    $overview = app(GetRealEstateOverview::class)($user->id);

    expect($overview->properties)->toBe([])
        ->and($overview->totalNetWorth)->toBe(0.0);
});
