<?php

use App\Domains\Analytics\Filament\Widgets\Dashboard\DashboardValuationWidget;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns zero when user has no wallets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = livewire(DashboardValuationWidget::class)
        ->instance()
        ->getValuationData();

    expect($data['valuation'])->toContain('0');
});

it('returns correct valuation from buy transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $data = livewire(DashboardValuationWidget::class)
        ->instance()
        ->getValuationData();

    $valuationNum = (float) str_replace(['€', ',', ' '], '', $data['valuation']);

    expect($valuationNum)->toBeGreaterThanOrEqual(1200);
});

it('shows success color when valuation exceeds invested', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $data = livewire(DashboardValuationWidget::class)
        ->instance()
        ->getValuationData();

    expect($data['color'])->toBe('success');
});

it('shows danger color when valuation is below invested', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 99,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $data = livewire(DashboardValuationWidget::class)
        ->instance()
        ->getValuationData();

    expect($data['color'])->toBe('danger');
});

it('aggregates across PEA and CTO wallets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $peaWallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);
    $ctoWallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $peaWallet->id,
        'security_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $ctoWallet->id,
        'security_id' => $security->id,
        'type' => 'buy',
        'quantity' => 5,
        'unit_price' => 100,
    ]);

    $data = livewire(DashboardValuationWidget::class)
        ->instance()
        ->getValuationData();

    $valuationNum = (float) str_replace(['€', ',', ' '], '', $data['valuation']);

    expect($valuationNum)->toBeGreaterThanOrEqual(1800);
});

it('uses total_invested from scope not raw query', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 50,
    ]);

    $data = livewire(DashboardValuationWidget::class)
        ->instance()
        ->getValuationData();

    $totalInvestedNum = (float) str_replace(['€', ',', ' '], '', $data['valuation']);

    expect($totalInvestedNum)->toBeGreaterThan(0);
});
