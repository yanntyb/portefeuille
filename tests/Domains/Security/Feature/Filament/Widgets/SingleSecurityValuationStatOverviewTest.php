<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\SingleSecurityValuationStatOverview;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns zero when record is null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = livewire(SingleSecurityValuationStatOverview::class)
        ->instance()
        ->getValuationData();

    expect($data['valuation'])->toContain('0');
});

it('computes valuation from single security stats', function () {
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

    $data = livewire(SingleSecurityValuationStatOverview::class, [
        'record' => $security,
        'walletId' => $wallet->id,
    ])
        ->instance()
        ->getValuationData();

    $valuationNum = (float) str_replace(['€', ',', ' '], '', $data['valuation']);

    expect($valuationNum)->toBeGreaterThanOrEqual(1200);
});

it('shows success color when valuation exceeds total invested', function () {
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

    $data = livewire(SingleSecurityValuationStatOverview::class, [
        'record' => $security,
        'walletId' => $wallet->id,
    ])
        ->instance()
        ->getValuationData();

    expect($data['color'])->toBe('success');
});

it('shows danger color when below total invested', function () {
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

    $data = livewire(SingleSecurityValuationStatOverview::class, [
        'record' => $security,
        'walletId' => $wallet->id,
    ])
        ->instance()
        ->getValuationData();

    expect($data['color'])->toBe('danger');
});

it('isolates to specified wallet', function () {
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

    $peaData = livewire(SingleSecurityValuationStatOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ])
        ->instance()
        ->getValuationData();

    $ctoData = livewire(SingleSecurityValuationStatOverview::class, [
        'record' => $security,
        'walletId' => $ctoWallet->id,
    ])
        ->instance()
        ->getValuationData();

    $peaNum = (float) str_replace(['€', ',', ' '], '', $peaData['valuation']);
    $ctoNum = (float) str_replace(['€', ',', ' '], '', $ctoData['valuation']);

    expect($peaNum)->toBeGreaterThan($ctoNum);
});
