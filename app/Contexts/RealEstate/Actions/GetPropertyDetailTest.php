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
        ->and($detail->monthlyCashFlows)->toHaveCount(20)
        ->and($detail->rentHistory[0]->month)->toBe('2026-08-01')
        ->and($detail->loan->monthlyPayment)->toBe(300.0)
        ->and($detail->expenseYears[0]->year)->toBe(2026)
        ->and($detail->expenseYears[0]->byCategory[0]['category'])->toBe('property_tax')
        ->and($detail->expenseYears[0]->byCategory[0]['label'])->toBe('Taxe foncière')
        ->and($detail->expenseYears[0]->byCategory[0]['amount'])->toBe(900.0)
        ->and($detail->metrics->grossYield)->toBe(0.0655);

    Carbon::setTestNow();
});

it('expose le capital restant du premier prêt, pas la somme de tous les prêts', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'acquisition_price' => 100000,
        'acquisition_fees' => 10000,
    ]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 12000, 'annual_rate' => 0.0, 'term_months' => 120, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 6000, 'annual_rate' => 0.0, 'term_months' => 60, 'start_date' => '2026-01-01', 'monthly_insurance' => 0]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $detail = app(GetPropertyDetail::class)($user->id, $property->id);

    // Premier prêt (2025-01-01) : 12000 sur 120 mois à 0 % = 100/mois, 19 échéances passées → 10100 restant.
    // Second prêt (2026-01-01) : 6000 sur 60 mois à 0 % = 100/mois, 7 échéances passées → 5300 restant.
    // Somme des deux (15400) : utilisée pour le patrimoine net, mais pas pour la fiche du prêt affiché.
    expect($detail->loan->remainingPrincipal)->toBe(10100.0)
        ->and($detail->netWorth)->toBe(104600.0);

    Carbon::setTestNow();
});

it('situates the loan: months paid, end date, interest already paid and still to come', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    // 1 200 € sur 12 mois à 0 % = 100 €/mois, première échéance en février 2026, dernière en janvier 2027.
    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 1200,
        'annual_rate' => 0.0,
        'term_months' => 12,
        'start_date' => '2026-01-01',
        'monthly_insurance' => 0,
    ]);

    $loan = app(GetPropertyDetail::class)($user->id, $property->id)->loan;

    expect($loan->monthsPaid)->toBe(7)
        ->and($loan->endDate)->toBe('2027-01-01')
        ->and($loan->principalRepaid)->toBe(700.0)
        ->and($loan->interestPaid)->toBe(0.0)
        ->and($loan->interestRemaining)->toBe(0.0);
});

it('splits the interest already paid from the interest still to come', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 12000,
        'annual_rate' => 0.06,
        'term_months' => 24,
        'start_date' => '2026-01-01',
        'monthly_insurance' => 0,
    ]);

    $loan = app(GetPropertyDetail::class)($user->id, $property->id)->loan;

    // Sept échéances payées sur vingt-quatre : les intérêts déjà réglés et ceux à venir se
    // recomposent en coût total, et la part payée est la plus faible des deux.
    expect($loan->monthsPaid)->toBe(7)
        ->and(round($loan->interestPaid + $loan->interestRemaining, 2))->toBe($loan->totalCost)
        ->and($loan->interestPaid)->toBeLessThan($loan->interestRemaining)
        ->and($loan->interestPaid)->toBeGreaterThan(0.0);
});

it('yields null for a property of another user', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();

    expect(app(GetPropertyDetail::class)($user->id, $property->id))->toBeNull();
});

it('étend la fenêtre du cash-flow à tout l\'historique des loyers', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    Lease::factory()->create([
        'property_id' => $property->id,
        'monthly_rent' => 600,
        'start_date' => '2025-01-01',
        'end_date' => null,
    ]);

    $flows = app(GetPropertyDetail::class)($user->id, $property->id)->monthlyCashFlows;

    // Vingt mois de bail : le cash-flow se lit sur la même profondeur que les loyers, sinon la
    // liste fusionnée afficherait des loyers sans net en regard.
    expect($flows)->toHaveCount(20)
        ->and($flows[0]->month)->toBe('2025-01-01')
        ->and($flows[19]->month)->toBe('2026-08-01');

    Carbon::setTestNow();
});

it('garde douze mois de cash-flow sur un bien sans bail', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);

    $flows = app(GetPropertyDetail::class)($user->id, $property->id)->monthlyCashFlows;

    expect($flows)->toHaveCount(12)
        ->and($flows[0]->month)->toBe('2025-09-01');

    Carbon::setTestNow();
});
