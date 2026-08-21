<?php

use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Ports\IncomePort;
use Illuminate\Support\Carbon;

function fakeMonthlyDividends(float $amount): void
{
    app()->bind(IncomePort::class, fn (): IncomePort => new class($amount) implements IncomePort
    {
        public function __construct(private float $amount) {}

        public function monthlyDividendsFor(int $userId): float
        {
            return $this->amount;
        }
    });
}

it('additionne les dividendes mensualisés et le locatif net', function () {
    Carbon::setTestNow('2026-08-21');
    fakeMonthlyDividends(102.0);
    ['user' => $user] = propertyFixture(['loan' => true]);

    $income = app(GetWealthIncome::class)($user->id);

    expect($income->monthlyDividends)->toBe(102.0)
        ->and($income->monthlyTotal)->toBe(round(102.0 + $income->monthlyRentalNet, 2));
});

it('vaut zéro sans dividende ni bien', function () {
    fakeMonthlyDividends(0.0);

    $income = app(GetWealthIncome::class)(999);

    expect($income->monthlyTotal)->toBe(0.0)
        ->and($income->monthlyRentalNet)->toBe(0.0);
});
