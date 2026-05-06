<?php

use App\Domains\Portfolio\Models\Wallet;
use App\Filament\Pages\WalletsConfigPage;

use function Pest\Livewire\livewire;

it('lists the authenticated user wallets', function () {
    $wallet = Wallet::factory()->create(['user_id' => auth()->id(), 'name' => 'Mon PEA']);

    livewire(WalletsConfigPage::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$wallet]);
});

it('renames a wallet', function () {
    $wallet = Wallet::factory()->create(['user_id' => auth()->id(), 'name' => 'Ancien nom']);

    livewire(WalletsConfigPage::class)
        ->callTableAction('edit', $wallet, data: ['name' => 'Nouveau nom']);

    expect($wallet->fresh()->name)->toBe('Nouveau nom');
});

it('deletes a wallet', function () {
    $wallet = Wallet::factory()->create(['user_id' => auth()->id()]);

    livewire(WalletsConfigPage::class)
        ->callTableAction('delete', $wallet);

    expect(Wallet::withoutGlobalScope('user')->find($wallet->id))->toBeNull();
});
