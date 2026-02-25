<?php

use App\Enums\AccountType;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Support\MarketCalendar;
use Carbon\Carbon;

it('has many transactions', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->count(3)->create(['security_id' => $security->id]);

    expect($security->transactions)->toHaveCount(3);
});

it('has many prices', function () {
    $security = Security::factory()->create();
    SecurityPrice::factory()->count(5)->create(['security_id' => $security->id]);

    expect($security->prices)->toHaveCount(5);
});

it('has a latest price', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-01',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-06-01',
        'close' => 150,
    ]);

    expect($security->latestPrice->close)->toBe('150.0000');
});

it('scopes securities by account type with aggregations', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 20,
        'unit_price' => 150,
        'fees' => 8,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 3,
    ]);

    $peaResults = Security::query()->forAccountType(AccountType::Pea, auth()->id())->get();

    expect($peaResults)->toHaveCount(1)
        ->and((float) $peaResults->first()->total_quantity)->toBe(30.0)
        ->and((float) $peaResults->first()->total_fees)->toBe(13.0)
        ->and((float) $peaResults->first()->total_invested)->toBe(4013.0);
});

it('does not include securities without transactions for the account type', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    $peaResults = Security::query()->forAccountType(AccountType::Pea, auth()->id())->get();

    expect($peaResults)->toBeEmpty();
});

it('has a current price when price is on last trading date', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => MarketCalendar::lastTradingDate(),
        'close' => 120,
    ]);

    expect($security->currentPrice)->not->toBeNull()
        ->and($security->currentPrice->close)->toBe('120.0000');
});

it('does not have a current price when price is before last trading date', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => MarketCalendar::lastTradingDate()->subDay(),
        'close' => 120,
    ]);

    expect($security->currentPrice)->toBeNull();
});

it('current price returns the most recent price since last trading date', function () {
    Carbon::setTestNow('2026-02-28'); // Saturday, lastTradingDate = Friday 27
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-02-27',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-02-28',
        'close' => 130,
    ]);

    expect($security->currentPrice->close)->toBe('130.0000');
});

it('has a today price only for today', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => today()->subDays(1),
        'close' => 120,
    ]);

    expect($security->todayPrice)->toBeNull();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => today(),
        'close' => 125,
    ]);

    $security->refresh();

    expect($security->todayPrice)->not->toBeNull()
        ->and($security->todayPrice->close)->toBe('125.0000');
});

it('computes PRU correctly', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    $result = Security::query()->forAccountType(AccountType::Pea, auth()->id())->first();

    expect((float) $result->pru)->toBe(150.0);
});
