<?php

use App\Enums\AccountType;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

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

    $peaResults = Security::query()->forAccountType(AccountType::Pea)->get();

    expect($peaResults)->toHaveCount(1)
        ->and((float) $peaResults->first()->total_quantity)->toBe(30.0)
        ->and((float) $peaResults->first()->total_fees)->toBe(13.0)
        ->and((float) $peaResults->first()->total_invested)->toBe(4013.0);
});

it('does not include securities without transactions for the account type', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    $peaResults = Security::query()->forAccountType(AccountType::Pea)->get();

    expect($peaResults)->toBeEmpty();
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

    $result = Security::query()->forAccountType(AccountType::Pea)->first();

    expect((float) $result->pru)->toBe(150.0);
});
