<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Filament\Pages\WalletPage;
use App\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('does not have a create action in the CTO wallet page', function () {
    $ctoWallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'security_id' => $security->id]);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->assertTableActionDoesNotExist('create');
});

it('can render the CTO wallet security edit page', function () {
    $ctoWallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $ctoWallet->id])
        ->assertOk()
        ->assertActionExists('editSecurity');
});

it('can update a security from CTO wallet', function () {
    $ctoWallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $ctoWallet->id])
        ->callAction('editSecurity', [
            'isin' => 'US5949181045',
            'name' => 'Microsoft Corporation',
            'ticker' => $security->ticker,
        ])
        ->assertNotified();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'isin' => 'US5949181045',
        'name' => 'Microsoft Corporation',
    ]);
});

it('can search CTO wallet securities by name', function () {
    $ctoWallet = Wallet::factory()->cto()->create();
    $target = Security::factory()->create(['name' => 'Tesla Inc.']);
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'security_id' => $target->id]);

    $other = Security::factory()->create(['name' => 'Amazon']);
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'security_id' => $other->id]);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->loadTable()
        ->searchTable('Tesla')
        ->assertCanSeeTableRecords(collect([$target]))
        ->assertCanNotSeeTableRecords(collect([$other]));
});

it('displays aggregated columns for a CTO wallet security', function () {
    $ctoWallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'wallet_id' => $ctoWallet->id,
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 3.00,
    ]);

    Transaction::factory()->create([
        'wallet_id' => $ctoWallet->id,
        'security_id' => $security->id,
        'quantity' => 15,
        'unit_price' => 250,
        'fees' => 7.00,
    ]);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertTableColumnExists('valuation')
        ->assertTableColumnExists('performance');
});
