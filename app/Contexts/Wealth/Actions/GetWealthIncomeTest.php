<?php

use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Ports\IncomePort;

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
    fakeMonthlyDividends(102.0);
    fakeRealEstate(0.0, 0.0, 45.5);

    $income = app(GetWealthIncome::class)(999);

    expect($income->monthlyDividends)->toBe(102.0)
        ->and($income->monthlyRentalNet)->toBe(45.5)
        ->and($income->monthlyTotal)->toBe(147.5);
});

it('vaut zéro sans dividende ni bien', function () {
    fakeMonthlyDividends(0.0);

    $income = app(GetWealthIncome::class)(999);

    expect($income->monthlyTotal)->toBe(0.0)
        ->and($income->monthlyRentalNet)->toBe(0.0);
});
