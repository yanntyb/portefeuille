<?php

use App\Enums\AccountType;
use App\Models\Security;
use App\Models\Transaction;

it('belongs to a security', function () {
    $security = Security::factory()->create();
    $transaction = Transaction::factory()->pea()->create(['security_id' => $security->id]);

    expect($transaction->security->id)->toBe($security->id);
});

it('casts date to Carbon instance', function () {
    $transaction = Transaction::factory()->pea()->create(['date' => '2024-06-15']);

    expect($transaction->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($transaction->date->format('Y-m-d'))->toBe('2024-06-15');
});

it('casts account_type to AccountType enum', function () {
    $transaction = Transaction::factory()->pea()->create();

    expect($transaction->account_type)->toBe(AccountType::Pea);
});

it('casts decimal fields correctly', function () {
    $transaction = Transaction::factory()->pea()->create([
        'quantity' => 10.5,
        'unit_price' => 99.1234,
        'fees' => 5.50,
    ]);

    $transaction->refresh();

    expect($transaction->quantity)->toBe('10.5000')
        ->and($transaction->unit_price)->toBe('99.1234')
        ->and($transaction->fees)->toBe('5.50');
});
