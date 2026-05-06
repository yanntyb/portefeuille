<?php

use App\Filament\Pages\WalletPage;
use App\Filament\Widgets\Securities\GainStatsOverview;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;

use function Pest\Livewire\livewire;

it('can render on the PEA list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->assertOk()
        ->assertSeeLivewire(GainStatsOverview::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);
    $ctoWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'CTO']);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->assertOk()
        ->assertSeeLivewire(GainStatsOverview::class);
});

it('computes valuation and plus-value correctly', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

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
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

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
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 150,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    expect($data['plusValuePositive'])->toBeTrue();
});

it('shows negative flag when plus-value is negative', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 80,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    expect($data['plusValuePositive'])->toBeFalse();
});

it('displays fees with percentage', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 10,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

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
    $peaWallet = Wallet::factory()->pea()->create();

    $widget = livewire(GainStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = $widget->instance()->getGainData();

    expect($data['plusValue'])->toContain('0');
});
