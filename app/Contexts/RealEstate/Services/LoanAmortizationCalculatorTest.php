<?php

use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->calculator = new LoanAmortizationCalculator;
});

it('builds a French amortization schedule with constant payments', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'));

    expect($schedule)->toHaveCount(2)
        ->and($schedule[0]->month)->toBe('2026-02-01')
        ->and($schedule[0]->payment)->toBe(507.51)
        ->and($schedule[0]->interest)->toBe(10.0)
        ->and($schedule[0]->principal)->toBe(497.51)
        ->and($schedule[0]->remaining)->toBe(502.49)
        ->and($schedule[1]->interest)->toBe(5.02)
        ->and($schedule[1]->principal)->toBe(502.49)
        ->and($schedule[1]->payment)->toBe(507.51)
        ->and($schedule[1]->remaining)->toBe(0.0);
});

it('sums repaid principal back to the borrowed amount', function () {
    $schedule = $this->calculator->schedule(150000.0, 0.024, 240, Carbon::parse('2026-01-01'));

    $repaid = array_sum(array_map(fn ($line): float => $line->principal, $schedule));

    expect($schedule)->toHaveCount(240)
        ->and(round($repaid, 2))->toBe(150000.0)
        ->and($schedule[239]->remaining)->toBe(0.0);
});

it('handles a zero interest rate as straight-line repayment', function () {
    $schedule = $this->calculator->schedule(1200.0, 0.0, 12, Carbon::parse('2026-01-01'));

    expect($schedule[0]->payment)->toBe(100.0)
        ->and($schedule[0]->interest)->toBe(0.0)
        ->and($schedule[11]->remaining)->toBe(0.0);
});

it('adds the insurance on top of the payment', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'), 20.0);

    expect($schedule[0]->insurance)->toBe(20.0)
        ->and($schedule[0]->payment)->toBe(527.51);
});

it('rounds the insurance to two decimals like every other field', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'), 19.999);

    expect($schedule[0]->insurance)->toBe(20.0)
        ->and($schedule[0]->payment)->toBe(527.51);
});

it('reads the remaining principal at any date', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'));

    expect($this->calculator->remainingAt($schedule, Carbon::parse('2026-01-20')))->toBe(1000.0)
        ->and($this->calculator->remainingAt($schedule, Carbon::parse('2026-02-10')))->toBe(502.49)
        ->and($this->calculator->remainingAt($schedule, Carbon::parse('2026-12-31')))->toBe(0.0);
});

it('remaining before any line needs the borrowed principal, so an empty schedule yields zero', function () {
    expect($this->calculator->remainingAt([], Carbon::parse('2026-01-01')))->toBe(0.0);
});

it('yields an empty schedule for a loan without any term, instead of dividing by zero', function () {
    expect($this->calculator->schedule(1000.0, 0.12, 0, Carbon::parse('2026-01-15')))->toBe([]);
});

it('résume un échéancier à une date donnée', function () {
    $calculator = new LoanAmortizationCalculator;
    // La première échéance tombe le mois suivant le départ (comportement inchangé de schedule()),
    // donc un départ au 2025-12-01 produit un échéancier de 2026-01-01 à 2026-12-01.
    $schedule = $calculator->schedule(1200.0, 0.0, 12, Carbon::parse('2025-12-01'), 0.0);

    $summary = $calculator->summaryOf($schedule, '2026-03-15');

    expect($summary['monthsPaid'])->toBe(3)
        ->and($summary['monthlyPayment'])->toBe(100.0)
        ->and($summary['endDate'])->toBe('2026-12-01')
        ->and($summary['principalRepaid'])->toBe(300.0)
        ->and($summary['interestPaid'])->toBe(0.0)
        ->and($summary['interestRemaining'])->toBe(0.0)
        ->and($summary['totalPaid'])->toBe(1200.0);
});
