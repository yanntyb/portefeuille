<?php

use App\Filament\Pages\WalletPage;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;

use function Pest\Livewire\livewire;

it('restores shown security ids from store for PEA page', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $securityA = Security::factory()->create();
    $securityB = Security::factory()->create();

    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityA->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityB->id]);

    SecurityPrice::factory()->create(['security_id' => $securityA->id, 'date' => now()]);
    SecurityPrice::factory()->create(['security_id' => $securityB->id, 'date' => now()]);

    $component = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);
    $component->call('restoreFromTableStore', ['shownSecurityIds' => [$securityA->id]]);

    expect($component->instance()->shownSecurityIds)
        ->toContain($securityA->id)
        ->not->toContain($securityB->id);
});

it('updates shown security ids when toggling', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $securityA = Security::factory()->create();
    $securityB = Security::factory()->create();

    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityA->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityB->id]);

    SecurityPrice::factory()->create(['security_id' => $securityA->id, 'date' => now()]);
    SecurityPrice::factory()->create(['security_id' => $securityB->id, 'date' => now()]);

    $component = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);

    $component->call('toggleSecurity', $securityB->id);

    expect($component->instance()->shownSecurityIds)
        ->toContain($securityA->id)
        ->not->toContain($securityB->id);
});

it('ignores persisted ids that no longer exist', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();

    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => now()]);

    $component = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);
    $component->call('restoreFromTableStore', ['shownSecurityIds' => [99999]]);

    expect($component->instance()->shownSecurityIds)
        ->not->toContain(99999);
});

it('restores independently per wallet', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $ctoWallet = Wallet::factory()->cto()->create();

    $peaSecurity = Security::factory()->create();
    $ctoSecurity = Security::factory()->create();

    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $peaSecurity->id]);
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'security_id' => $ctoSecurity->id]);

    SecurityPrice::factory()->create(['security_id' => $peaSecurity->id, 'date' => now()]);
    SecurityPrice::factory()->create(['security_id' => $ctoSecurity->id, 'date' => now()]);

    $peaComponent = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);
    $ctoComponent = livewire(WalletPage::class, ['walletId' => $ctoWallet->id]);

    $peaComponent->call('restoreFromTableStore', ['shownSecurityIds' => []]);
    $ctoComponent->call('restoreFromTableStore', ['shownSecurityIds' => [$ctoSecurity->id]]);

    expect($peaComponent->instance()->shownSecurityIds)->toBeEmpty()
        ->and($ctoComponent->instance()->shownSecurityIds)->toContain($ctoSecurity->id);
});
