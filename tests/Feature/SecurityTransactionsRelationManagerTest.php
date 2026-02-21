<?php

use App\Enums\AccountType;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Resources\Securities\RelationManagers\TransactionsRelationManager;
use App\Models\Security;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\assertDatabaseHas;
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

it('can create a transaction from the relation manager', function () {
    $security = Security::factory()->create();

    livewire(TransactionsRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditPeaSecurity::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'account_type' => AccountType::Pea->value,
            'date' => '2025-06-15',
            'quantity' => 10,
            'unit_price' => 50,
            'fees' => 2.50,
        ])
        ->assertNotified();

    assertDatabaseHas(Transaction::class, [
        'security_id' => $security->id,
        'account_type' => AccountType::Pea->value,
        'quantity' => 10,
    ]);
});
