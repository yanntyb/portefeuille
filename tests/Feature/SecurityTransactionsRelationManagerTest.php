<?php

use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Resources\Securities\RelationManagers\TransactionsRelationManager;
use App\Models\Security;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('can render the relation manager on the edit page', function () {
    $security = Security::factory()->create();

    livewire(EditPeaSecurity::class, ['record' => $security->id])
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
        'pageClass' => EditPeaSecurity::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($transactions)
        ->assertCanNotSeeTableRecords(collect([$otherTransaction]));
});

it('does not have a create action in the relation manager', function () {
    $security = Security::factory()->create();

    livewire(TransactionsRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditPeaSecurity::class,
    ])
        ->assertTableActionDoesNotExist('create');
});
