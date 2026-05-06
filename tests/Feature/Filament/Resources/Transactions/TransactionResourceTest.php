<?php

use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Security;
use App\Models\Transaction;
use App\Models\Wallet;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('can render the list page', function () {
    $transactions = Transaction::factory()->pea()->count(3)->create();

    livewire(ListTransactions::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($transactions);
});

it('can render the create page', function () {
    livewire(CreateTransaction::class)
        ->assertOk();
});

it('can create a PEA transaction', function () {
    $wallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'wallet_id' => $wallet->id,
            'date' => '2025-06-15',
            'security_id' => $security->id,
            'quantity' => 10,
            'unit_price' => 100.50,
            'fees' => 1.99,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Transaction::class, [
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'quantity' => 10,
        'fees' => 1.99,
    ]);
});

it('can create a CTO transaction with broker', function () {
    $wallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'wallet_id' => $wallet->id,
            'date' => '2025-06-15',
            'security_id' => $security->id,
            'broker' => 'Degiro',
            'quantity' => 5,
            'unit_price' => 150.00,
            'fees' => 2.00,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Transaction::class, [
        'wallet_id' => $wallet->id,
        'broker' => 'Degiro',
    ]);
});

it('can create a Livret transaction', function () {
    $wallet = Wallet::factory()->livret()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'wallet_id' => $wallet->id,
            'date' => '2025-06-01',
            'notes' => 'Versement mensuel',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Transaction::class, [
        'wallet_id' => $wallet->id,
        'notes' => 'Versement mensuel',
        'security_id' => null,
        'broker' => null,
    ]);
});

it('can render the edit page', function () {
    $transaction = Transaction::factory()->pea()->create();

    livewire(EditTransaction::class, ['record' => $transaction->id])
        ->assertOk();
});

it('can update a transaction', function () {
    $transaction = Transaction::factory()->livret()->create([
        'notes' => 'Ancien texte',
    ]);

    livewire(EditTransaction::class, ['record' => $transaction->id])
        ->fillForm([
            'notes' => 'Nouveau texte',
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Transaction::class, [
        'id' => $transaction->id,
        'notes' => 'Nouveau texte',
    ]);
});

it('validates that date is required', function () {
    $wallet = Wallet::factory()->pea()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'wallet_id' => $wallet->id,
            'date' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['date' => 'required']);
});

it('validates that wallet is required', function () {
    livewire(CreateTransaction::class)
        ->fillForm([
            'wallet_id' => null,
            'date' => '2025-06-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['wallet_id' => 'required']);
});

it('can filter transactions by wallet', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $livretWallet = Wallet::factory()->livret()->create();

    $peaTransactions = Transaction::factory()->count(2)->create(['wallet_id' => $peaWallet->id]);
    $livretTransactions = Transaction::factory()->livret()->count(2)->create(['wallet_id' => $livretWallet->id]);

    livewire(ListTransactions::class)
        ->loadTable()
        ->assertCanSeeTableRecords($peaTransactions->merge($livretTransactions))
        ->filterTable('wallet_id', $peaWallet->id)
        ->assertCanSeeTableRecords($peaTransactions)
        ->assertCanNotSeeTableRecords($livretTransactions);
});
