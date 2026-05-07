<?php

use App\Domains\Portfolio\Enums\CurrencyModificationUnit;
use App\Domains\Portfolio\Enums\FeeScope;
use App\Domains\Portfolio\Enums\FrequencyUnit;
use App\Domains\Portfolio\Filament\Pages\WalletsConfigPage;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Models\WalletFee;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('renders wallets config page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(WalletsConfigPage::class)
        ->assertSuccessful()
        ->assertSeeText('Configuration des portefeuilles');
});

it('wallet can have multiple fees', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id]);

    WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Currency,
    ]);

    WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Percentage,
        'scope' => FeeScope::TotalValuation,
    ]);

    expect($wallet->fees)->toHaveCount(2);
});

it('percentage fee supports scope', function () {
    $wallet = Wallet::factory()->create();

    $fee = WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Percentage,
        'scope' => FeeScope::TotalValuation,
        'frequency' => null,
    ]);

    expect($fee->scope)->toBe(FeeScope::TotalValuation)
        ->and($fee->frequency)->toBeNull();
});

it('currency fee supports frequency', function () {
    $wallet = Wallet::factory()->create();

    $fee = WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Currency,
        'scope' => null,
        'frequency' => FrequencyUnit::Monthly,
    ]);

    expect($fee->frequency)->toBe(FrequencyUnit::Monthly)
        ->and($fee->scope)->toBeNull();
});

it('wallet fees can be deleted and replaced', function () {
    $wallet = Wallet::factory()->create();

    $oldFee = WalletFee::factory()->create(['wallet_id' => $wallet->id]);
    expect($wallet->fees)->toHaveCount(1);

    $wallet->fees()->delete();
    WalletFee::factory()->create(['wallet_id' => $wallet->id, 'name' => 'New Fee']);

    $wallet->refresh();

    expect($wallet->fees)->toHaveCount(1)
        ->and($wallet->fees->first()->name)->toBe('New Fee')
        ->and(WalletFee::find($oldFee->id))->toBeNull();
});

it('wallet name can be updated', function () {
    $wallet = Wallet::factory()->create(['name' => 'Old Name']);

    $wallet->update(['name' => 'New Name']);

    expect($wallet->fresh()->name)->toBe('New Name');
});

it('wallet can be deleted', function () {
    $wallet = Wallet::factory()->create();
    $walletId = $wallet->id;

    $wallet->delete();

    expect(Wallet::find($walletId))->toBeNull();
});
