<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetRealEstateIncome;
use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use Illuminate\Support\Carbon;

/** Un bien loué 600 €/mois depuis 2025, échéance de 300 € et 1 200 € de travaux en juin 2025. */
function rentalIncomeProperty(User $user): Property
{
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'acquisition_date' => '2025-01-01',
        'acquisition_price' => 100000,
        'acquisition_fees' => 8000,
    ]);

    Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 600, 'start_date' => '2025-01-01', 'end_date' => null]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 90000, 'annual_rate' => 0.0, 'term_months' => 300, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2025-06-15', 'amount' => 1200, 'category' => ExpenseCategory::Works]);

    return $property;
}

it('sums the rental income of the last twelve months', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    rentalIncomeProperty($user);

    $income = app(GetRealEstateIncome::class)($user->id);

    // Septembre 2025 à août 2026 : douze loyers, douze échéances, aucune charge.
    expect($income->rents12m)->toBe(7200.0)
        ->and($income->expenses12m)->toBe(0.0)
        ->and($income->loanPayments12m)->toBe(3600.0)
        ->and($income->net12m)->toBe(3600.0);

    Carbon::setTestNow();
});

it('breaks the rental income down by year, the most recent first', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    rentalIncomeProperty($user);

    $income = app(GetRealEstateIncome::class)($user->id);

    // 2025 : douze loyers, onze échéances — la première tombe en février — et les travaux.
    expect($income->years)->toHaveCount(2)
        ->and($income->years[0]->year)->toBe(2026)
        ->and($income->years[0]->rents)->toBe(4800.0)
        ->and($income->years[0]->loanPayments)->toBe(2400.0)
        ->and($income->years[0]->net)->toBe(2400.0)
        ->and($income->years[1]->year)->toBe(2025)
        ->and($income->years[1]->rents)->toBe(7200.0)
        ->and($income->years[1]->expenses)->toBe(1200.0)
        ->and($income->years[1]->loanPayments)->toBe(3300.0)
        ->and($income->years[1]->net)->toBe(2700.0);

    Carbon::setTestNow();
});

it('yields an empty income when the user owns nothing', function () {
    $income = app(GetRealEstateIncome::class)(User::factory()->create()->id);

    expect($income->net12m)->toBe(0.0)
        ->and($income->years)->toBe([]);
});
