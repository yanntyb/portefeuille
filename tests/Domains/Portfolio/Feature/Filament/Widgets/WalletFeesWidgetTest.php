<?php

use App\Domains\Portfolio\Enums\CurrencyModificationUnit;
use App\Domains\Portfolio\Enums\FeeScope;
use App\Domains\Portfolio\Enums\FrequencyUnit;
use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Filament\Widgets\WalletFeesWidget;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Models\WalletFee;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns zeros when no wallet provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(WalletFeesWidget::class);

    $data = invade($widget->instance())->getFeesData();

    expect($data['transactionFees'])->toBe('€0.00')
        ->and($data['transactionFeesPercentage'])->toBe('0 %')
        ->and($data['annualFees'])->toBe('€0.00')
        ->and($data['walletFees'])->toBeEmpty();
});

it('returns zeros when wallet has no securities', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(WalletFeesWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ]);

    $data = invade($widget->instance())->getFeesData();

    expect($data['transactionFees'])->toBe('€0.00')
        ->and($data['annualFees'])->toBe('€0.00');
});

it('calculates transaction fees', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $security = Security::factory()->create();
    
SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 100, 'date' => now()]);

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 25.50,
    ]);

    $widget = livewire(WalletFeesWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ]);

    $data = invade($widget->instance())->getFeesData();

    expect($data['transactionFees'])->toContain('25')
        ->and($data['transactionFeesPercentage'])->toContain('2');
});

it('calculates percentage fees on valuation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $security = Security::factory()->create();
    
SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 100, 'date' => now()]);

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Percentage,
        'value' => 1,
        'scope' => FeeScope::TotalValuation,
    ]);

    $widget = livewire(WalletFeesWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ]);

    $data = invade($widget->instance())->getFeesData();

    // 1% of valuation (1000) = 10 EUR annually
    expect($data['annualFees'])->toContain('10');
});

it('calculates currency fees with frequency', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $security = Security::factory()->create();
    
SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 100, 'date' => now()]);

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Currency,
        'value' => 5,
        'frequency' => FrequencyUnit::Monthly,
    ]);

    $widget = livewire(WalletFeesWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ]);

    $data = invade($widget->instance())->getFeesData();

    // 5 EUR monthly = 60 EUR annually
    expect($data['annualFees'])->toContain('60');
});

it('calculates percentage fees on unrealized gain', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $security = Security::factory()->create();
    
SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 150, 'date' => now()]);

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Percentage,
        'value' => 10,
        'scope' => FeeScope::UnrealizedGain,
    ]);

    $widget = livewire(WalletFeesWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ]);

    $data = invade($widget->instance())->getFeesData();

    // Unrealized gain = 1500 - 1000 = 500, 10% = 50 EUR
    expect($data['annualFees'])->toContain('50');
});

it('calculates quarterly fees correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $security = Security::factory()->create();
    
SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 100, 'date' => now()]);

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    WalletFee::factory()->create([
        'wallet_id' => $wallet->id,
        'unit' => CurrencyModificationUnit::Currency,
        'value' => 10,
        'frequency' => FrequencyUnit::Quarterly,
    ]);

    $widget = livewire(WalletFeesWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ]);

    $data = invade($widget->instance())->getFeesData();

    // 10 EUR quarterly = 40 EUR annually
    expect($data['annualFees'])->toContain('40');
});
