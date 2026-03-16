<?php

use App\Filament\Pages\WalletPage;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Security;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('redirects to login when not authenticated', function () {
    auth()->logout();

    get('/admin')
        ->assertRedirect('/admin/login');
});

it('scopes transactions to the authenticated user', function () {
    $myWallet = Wallet::factory()->pea()->create();
    $myTransaction = Transaction::factory()->create([
        'user_id' => auth()->id(),
        'wallet_id' => $myWallet->id,
    ]);

    $otherUser = User::factory()->create();
    $otherWallet = Wallet::factory()->pea()->create(['user_id' => $otherUser->id]);
    $otherTransaction = Transaction::factory()->create([
        'user_id' => $otherUser->id,
        'wallet_id' => $otherWallet->id,
    ]);

    livewire(ListTransactions::class)
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$myTransaction]))
        ->assertCanNotSeeTableRecords(collect([$otherTransaction]));
});

it('scopes PEA securities to the authenticated user', function () {
    $myWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create([
        'user_id' => auth()->id(),
        'wallet_id' => $myWallet->id,
        'security_id' => $security->id,
    ]);

    $otherUser = User::factory()->create();
    $otherWallet = Wallet::factory()->pea()->create(['user_id' => $otherUser->id]);
    $otherSecurity = Security::factory()->create();
    Transaction::factory()->create([
        'user_id' => $otherUser->id,
        'wallet_id' => $otherWallet->id,
        'security_id' => $otherSecurity->id,
    ]);

    livewire(WalletPage::class, ['walletId' => $myWallet->id])
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertCanNotSeeTableRecords(collect([$otherSecurity]));
});

it('scopes CTO securities to the authenticated user', function () {
    $myWallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create([
        'user_id' => auth()->id(),
        'wallet_id' => $myWallet->id,
        'security_id' => $security->id,
    ]);

    $otherUser = User::factory()->create();
    $otherWallet = Wallet::factory()->cto()->create(['user_id' => $otherUser->id]);
    $otherSecurity = Security::factory()->create();
    Transaction::factory()->create([
        'user_id' => $otherUser->id,
        'wallet_id' => $otherWallet->id,
        'security_id' => $otherSecurity->id,
    ]);

    livewire(WalletPage::class, ['walletId' => $myWallet->id])
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertCanNotSeeTableRecords(collect([$otherSecurity]));
});

it('assigns user_id when creating a transaction', function () {
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
        'user_id' => auth()->id(),
        'security_id' => $security->id,
    ]);
});
