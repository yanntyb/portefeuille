<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render the PEA wallet page with only PEA securities', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $peaSecurity = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'asset_id' => $peaSecurity->id]);

    $ctoWallet = Wallet::factory()->cto()->create();
    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'asset_id' => $ctoSecurity->id]);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$peaSecurity]))
        ->assertCanNotSeeTableRecords(collect([$ctoSecurity]));
});

it('can render the CTO wallet page with only CTO securities', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $peaSecurity = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'asset_id' => $peaSecurity->id]);

    $ctoWallet = Wallet::factory()->cto()->create();
    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'asset_id' => $ctoSecurity->id]);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$ctoSecurity]))
        ->assertCanNotSeeTableRecords(collect([$peaSecurity]));
});

it('displays aggregated columns for a wallet security', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5.00,
    ]);

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'asset_id' => $security->id,
        'quantity' => 20,
        'unit_price' => 150,
        'fees' => 8.00,
    ]);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertTableColumnExists('valuation')
        ->assertTableColumnExists('performance');
});

it('does not have a create action in the wallet list page', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'asset_id' => $security->id]);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->assertTableActionDoesNotExist('create');
});

it('can render the wallet security edit page', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $peaWallet->id])
        ->assertOk()
        ->assertActionExists('editSecurity');
});

it('can update a security from wallet', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $peaWallet->id])
        ->callAction('editSecurity', [
            'isin' => 'US1667641005',
            'name' => 'Chevron Corporation',
            'ticker' => $security->ticker,
        ])
        ->assertNotified();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'isin' => 'US1667641005',
        'name' => 'Chevron Corporation',
    ]);
});

it('can search wallet securities by name', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $target = Security::factory()->create(['name' => 'Amundi MSCI World']);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'asset_id' => $target->id]);

    $other = Security::factory()->create(['name' => 'Chevron Corporation']);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'asset_id' => $other->id]);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->loadTable()
        ->searchTable('Amundi')
        ->assertCanSeeTableRecords(collect([$target]))
        ->assertCanNotSeeTableRecords(collect([$other]));
});
