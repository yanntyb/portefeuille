<?php

use App\Domains\Portfolio\Filament\Pages\WalletsConfigPage;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('lists the authenticated user wallets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => auth()->id(), 'name' => 'Mon PEA']);

    livewire(WalletsConfigPage::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$wallet]);
});

it('renames a wallet', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => auth()->id(), 'name' => 'Ancien nom']);

    livewire(WalletsConfigPage::class)
        ->callTableAction('edit', $wallet, data: ['name' => 'Nouveau nom']);

    expect($wallet->fresh()->name)->toBe('Nouveau nom');
});

it('deletes a wallet', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => auth()->id()]);

    livewire(WalletsConfigPage::class)
        ->callTableAction('delete', $wallet);

    expect(Wallet::withoutGlobalScope('user')->find($wallet->id))->toBeNull();
});
