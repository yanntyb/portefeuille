<?php

use App\Contexts\RealEstate\Datas\PropertyFinancialsData;
use App\Contexts\RealEstate\Services\PropertyMetricsCalculator;

beforeEach(function () {
    $this->calculator = new PropertyMetricsCalculator;
});

it('computes every pre-tax metric', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 100000.0,
        acquisitionFees: 10000.0,
        currentMonthlyRent: 600.0,
        rents12m: 6600.0,
        expenses12m: 1200.0,
        loanPayments12m: 4800.0,
        borrowedPrincipal: 90000.0,
        remainingPrincipal: 85000.0,
        currentValue: 120000.0,
    ));

    expect($metrics->grossYield)->toBe(0.0655)
        ->and($metrics->netYield)->toBe(0.0491)
        ->and($metrics->annualCashFlow)->toBe(600.0)
        ->and($metrics->cashOnCash)->toBe(0.03)
        ->and($metrics->ltv)->toBe(0.7083);
});

it('leaves cash-on-cash out when there is no down payment', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 100000.0,
        acquisitionFees: 0.0,
        currentMonthlyRent: 600.0,
        rents12m: 7200.0,
        expenses12m: 0.0,
        loanPayments12m: 6000.0,
        borrowedPrincipal: 100000.0,
        remainingPrincipal: 95000.0,
        currentValue: 100000.0,
    ));

    expect($metrics->cashOnCash)->toBeNull();
});

it('leaves ltv out without a current value', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 100000.0,
        acquisitionFees: 10000.0,
        currentMonthlyRent: 600.0,
        rents12m: 6600.0,
        expenses12m: 1200.0,
        loanPayments12m: 4800.0,
        borrowedPrincipal: 90000.0,
        remainingPrincipal: 85000.0,
        currentValue: 0.0,
    ));

    expect($metrics->ltv)->toBeNull();
});

it('leaves both yields out when the acquisition cost is zero', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 0.0,
        acquisitionFees: 0.0,
        currentMonthlyRent: 600.0,
        rents12m: 7200.0,
        expenses12m: 0.0,
        loanPayments12m: 0.0,
        borrowedPrincipal: 0.0,
        remainingPrincipal: 0.0,
        currentValue: 100000.0,
    ));

    expect($metrics->grossYield)->toBeNull()
        ->and($metrics->netYield)->toBeNull();
});
