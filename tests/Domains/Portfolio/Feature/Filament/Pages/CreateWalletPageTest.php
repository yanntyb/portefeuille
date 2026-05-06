<?php

use App\Domains\Portfolio\Filament\Pages\CreateWalletPage;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('creates a wallet and redirects after submitting the form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(CreateWalletPage::class)
        ->fillForm(['name' => 'Mon PEA'])
        ->call('create')
        ->assertRedirectContains('wallets');

    $wallet = Wallet::withoutGlobalScope('user')
        ->where('user_id', auth()->id())
        ->first();

    expect($wallet)->not->toBeNull()
        ->and($wallet->name)->toBe('Mon PEA')
        ->and($wallet->user_id)->toBe(auth()->id());
});
