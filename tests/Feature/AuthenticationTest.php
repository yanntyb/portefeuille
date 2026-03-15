<?php

use App\Enums\AccountType;
use App\Filament\Pages\CtoPage;
use App\Filament\Pages\PeaPage;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Security;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('redirects to login when not authenticated', function () {
    auth()->logout();

    get('/admin')
        ->assertRedirect('/admin/login');
});

it('scopes transactions to the authenticated user', function () {
    $myTransaction = Transaction::factory()->pea()->create([
        'user_id' => auth()->id(),
    ]);

    $otherUser = User::factory()->create();
    $otherTransaction = Transaction::factory()->pea()->create([
        'user_id' => $otherUser->id,
    ]);

    livewire(ListTransactions::class)
        ->assertCanSeeTableRecords(collect([$myTransaction]))
        ->assertCanNotSeeTableRecords(collect([$otherTransaction]));
});

it('scopes PEA securities to the authenticated user', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'user_id' => auth()->id(),
        'security_id' => $security->id,
    ]);

    $otherSecurity = Security::factory()->create();
    $otherUser = User::factory()->create();
    Transaction::factory()->pea()->create([
        'user_id' => $otherUser->id,
        'security_id' => $otherSecurity->id,
    ]);

    livewire(PeaPage::class)
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertCanNotSeeTableRecords(collect([$otherSecurity]));
});

it('scopes CTO securities to the authenticated user', function () {
    $security = Security::factory()->create();

    Transaction::factory()->cto()->create([
        'user_id' => auth()->id(),
        'security_id' => $security->id,
    ]);

    $otherSecurity = Security::factory()->create();
    $otherUser = User::factory()->create();
    Transaction::factory()->cto()->create([
        'user_id' => $otherUser->id,
        'security_id' => $otherSecurity->id,
    ]);

    livewire(CtoPage::class)
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertCanNotSeeTableRecords(collect([$otherSecurity]));
});

it('assigns user_id when creating a transaction', function () {
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
        'user_id' => auth()->id(),
        'security_id' => $security->id,
    ]);
});
