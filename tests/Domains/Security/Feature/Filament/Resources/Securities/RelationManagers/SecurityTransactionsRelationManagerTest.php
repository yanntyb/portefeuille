<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Models\Security;
use App\Filament\Resources\Securities\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;

use function Pest\Livewire\livewire;

it('can render the relation manager on the edit page', function () {
    $security = Security::factory()->create();

    livewire(EditWalletSecurity::class, ['record' => $security->id])
        ->assertSeeLivewire(TransactionsRelationManager::class);
});

it('can list transactions for a security', function () {
    $security = Security::factory()->create();
    $transactions = Transaction::factory()
        ->count(3)
        ->pea()
        ->create(['security_id' => $security->id]);

    $otherTransaction = Transaction::factory()->pea()->create();

    livewire(TransactionsRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditWalletSecurity::class,
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($transactions)
        ->assertCanNotSeeTableRecords(collect([$otherTransaction]));
});

it('does not have a create action in the relation manager', function () {
    $security = Security::factory()->create();

    livewire(TransactionsRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditWalletSecurity::class,
    ])
        ->assertTableActionDoesNotExist('create');
});
