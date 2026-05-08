<?php

use App\Domains\Portfolio\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Filament\Resources\SecurityBase\RelationManagers\TransactionsRelationManager;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

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
        ->create(['asset_id' => $security->id]);

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
