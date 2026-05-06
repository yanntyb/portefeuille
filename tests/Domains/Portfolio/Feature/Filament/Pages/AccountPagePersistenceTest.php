<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;

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
    $component->call('restoreFromTableStore', ['hiddenSecurityIds' => [$securityB->id]]);

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
    $component->call('restoreFromTableStore', ['hiddenSecurityIds' => [99999]]);

    expect($component->instance()->shownSecurityIds)
        ->not->toContain(99999)
        ->toContain($security->id);
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

    $peaComponent->call('restoreFromTableStore', ['hiddenSecurityIds' => [$peaSecurity->id]]);
    $ctoComponent->call('restoreFromTableStore', ['hiddenSecurityIds' => []]);

    expect($peaComponent->instance()->shownSecurityIds)
        ->not->toContain($peaSecurity->id)
        ->and($ctoComponent->instance()->shownSecurityIds)
        ->toContain($ctoSecurity->id);
});
