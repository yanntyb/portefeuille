<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\GainStatsOverview;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('computes valuation and plus-value correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = $widget->instance()->getGainData();

    // Valuation = 10 * 120 = 1200, Invested = 10*100+5 = 1005, PV = 195
    expect($data['plusValue'])->toContain('195')
        ->and($data['fees'])->toContain('5');
});

it('displays percentage alongside plus-value', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    // Plus-value = 1200 - 1000 = 200, percentage = 20%
    expect($data['plusValue'])->toContain('200')
        ->and($data['plusValuePercentage'])->toContain('20')
        ->and($data['plusValuePercentage'])->toContain('%');
});

it('shows positive flag when plus-value is positive', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 150,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    expect($data['plusValuePositive'])->toBeTrue();
});

it('shows negative flag when plus-value is negative', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 80,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    expect($data['plusValuePositive'])->toBeFalse();
});

it('displays fees with percentage', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 10,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    // Fees = 10, totalInvested = 10*100 + 10 = 1010, percentage = 10/1010 * 100 ≈ 0.99%
    expect($data['fees'])->toContain('10')
        ->and($data['feesPercentage'])->toContain('%');
});

it('returns zero stats when no securities exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $peaWallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = $widget->instance()->getGainData();

    expect($data['plusValue'])->toContain('0');
});
