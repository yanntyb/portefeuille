<?php

use App\Domains\Analytics\Filament\Widgets\Dashboard\DashboardGainStatsOverview;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('shows zero gains when user has no wallets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = livewire(DashboardGainStatsOverview::class)
        ->instance()
        ->getGainData();

    expect($data['plusValue'])->toContain('0')
        ->and($data['realizedGain'])->toContain('0');
});

it('calculates unrealized gains from buy transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 150,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 10,
    ]);

    $widget = livewire(DashboardGainStatsOverview::class)->instance();
    $data = $widget->getGainData();

    $plusValueNum = (float) str_replace(['€', ',', ' '], '', $data['plusValue']);

    expect($plusValueNum)->toBeGreaterThan(0);
});

it('calculates realized gains from sell transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 150,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'sell',
        'quantity' => 5,
        'unit_price' => 120,
        'fees' => 0,
        'realized_gain' => 100,
    ]);

    $widget = livewire(DashboardGainStatsOverview::class)->instance();
    $data = $widget->getGainData();

    $realizedGainNum = (float) str_replace(['€', ',', ' '], '', $data['realizedGain']);

    expect($realizedGainNum)->toBeGreaterThan(0);
});

it('includes fees in calculations', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 100,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 50,
    ]);

    $widget = livewire(DashboardGainStatsOverview::class)->instance();
    $data = $widget->getGainData();

    expect($data['fees'])->toContain('50');
});
