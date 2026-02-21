<?php

use App\Enums\AccountType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Security;
use App\Models\Transaction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('can render the list page', function () {
    $transactions = Transaction::factory()->pea()->count(3)->create();

    livewire(ListTransactions::class)
        ->assertOk()
        ->assertCanSeeTableRecords($transactions);
});

it('can render the create page', function () {
    livewire(CreateTransaction::class)
        ->assertOk();
});

it('can create a PEA transaction', function () {
    $security = Security::factory()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Pea->value,
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
        'account_type' => 'pea',
        'security_id' => $security->id,
        'quantity' => 10,
        'fees' => 1.99,
    ]);
});

it('can create a CTO transaction with broker', function () {
    $security = Security::factory()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Cto->value,
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
        'account_type' => 'cto',
        'broker' => 'Degiro',
    ]);
});

it('can create a Livret transaction', function () {
    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Livret->value,
            'date' => '2025-06-01',
            'notes' => 'Versement mensuel',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Transaction::class, [
        'account_type' => 'livret',
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
    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Pea->value,
            'date' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['date' => 'required']);
});

it('validates that account_type is required', function () {
    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => null,
            'date' => '2025-06-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['account_type' => 'required']);
});

it('can filter transactions by account type', function () {
    $peaTransactions = Transaction::factory()->pea()->count(2)->create();
    $livretTransactions = Transaction::factory()->livret()->count(2)->create();

    livewire(ListTransactions::class)
        ->assertCanSeeTableRecords($peaTransactions->merge($livretTransactions))
        ->filterTable('account_type', AccountType::Pea->value)
        ->assertCanSeeTableRecords($peaTransactions)
        ->assertCanNotSeeTableRecords($livretTransactions);
});
