<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetLoanSchedule;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;

it('yields the amortization schedule of the property loan', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 1200, 'annual_rate' => 0.0, 'term_months' => 12, 'start_date' => '2026-01-01', 'monthly_insurance' => 0]);

    $schedule = app(GetLoanSchedule::class)($user->id, $property->id);

    expect($schedule)->toHaveCount(12)
        ->and($schedule[0]->payment)->toBe(100.0);
});

it('yields an empty schedule without a loan or for a foreign property', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    $foreign = Property::factory()->create();

    expect(app(GetLoanSchedule::class)($user->id, $property->id))->toBe([])
        ->and(app(GetLoanSchedule::class)($user->id, $foreign->id))->toBe([]);
});
