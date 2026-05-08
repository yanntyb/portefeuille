<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\ValuationStatOverview;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns zero when no tablePageClass', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $data = livewire(ValuationStatOverview::class)
        ->instance()
        ->getValuationData();

    expect($data['valuation'])->toContain('0');
});

it('computes valuation from wallet page table query', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $data = livewire(ValuationStatOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ])
        ->instance()
        ->getValuationData();

    $valuationNum = (float) str_replace(['€', ',', ' '], '', $data['valuation']);

    expect($valuationNum)->toBeGreaterThanOrEqual(1200);
});

it('shows success color when valuation exceeds invested', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $data = livewire(ValuationStatOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ])
        ->instance()
        ->getValuationData();

    expect($data['color'])->toBe('success');
});

it('shows danger color when below invested', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 99,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $data = livewire(ValuationStatOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ])
        ->instance()
        ->getValuationData();

    expect($data['color'])->toBe('danger');
});

it('filters to shownSecurityIds when set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    
SecurityPrice::factory()->create([
        'asset_id' => $security1->id,
        'close' => 120,
        'date' => now(),
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security2->id,
        'close' => 120,
        'date' => now(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security1->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security2->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $widget = livewire(ValuationStatOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $wallet->id,
    ])->instance();

    $widget->shownSecurityIds = [$security1->id];

    $data = $widget->getValuationData();

    $valuationNum = (float) str_replace(['€', ',', ' '], '', $data['valuation']);

    expect($valuationNum)->toBeLessThan(2400)
        ->and($valuationNum)->toBeGreaterThanOrEqual(1200);
});
