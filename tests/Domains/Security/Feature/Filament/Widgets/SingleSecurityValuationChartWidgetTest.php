<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\SingleSecurityValuationChartWidget;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns empty when no record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(SingleSecurityValuationChartWidget::class)->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});

it('returns empty when no transactions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    $widget = livewire(SingleSecurityValuationChartWidget::class, [
        'record' => $security,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});

it('returns empty when no prices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $widget = livewire(SingleSecurityValuationChartWidget::class, [
        'record' => $security,
        'walletId' => $wallet->id,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});

it('builds chart with valuation dataset', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    $transactionDate = now()->subDays(5);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => $transactionDate,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 110,
        'date' => now(),
    ]);

    $widget = livewire(SingleSecurityValuationChartWidget::class, [
        'record' => $security,
        'walletId' => $wallet->id,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->not->toBeEmpty()
        ->and($data['labels'])->not->toBeEmpty();
});

it('filters transactions to specified walletId', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet1 = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $wallet2 = Wallet::factory()->cto()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    $transactionDate = now()->subDays(5);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet1->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => $transactionDate,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet2->id,
        'asset_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 100,
        'date' => $transactionDate,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 110,
        'date' => now(),
    ]);

    $widget1 = livewire(SingleSecurityValuationChartWidget::class, [
        'record' => $security,
        'walletId' => $wallet1->id,
    ])->instance();
    $data1 = invade($widget1)->getData();

    $widget2 = livewire(SingleSecurityValuationChartWidget::class, [
        'record' => $security,
        'walletId' => $wallet2->id,
    ])->instance();
    $data2 = invade($widget2)->getData();

    expect($data1['datasets'])->not->toBeEmpty()
        ->and($data2['datasets'])->not->toBeEmpty();
});

it('uses all transactions when walletId is null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet1 = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $wallet2 = Wallet::factory()->cto()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    $transactionDate = now()->subDays(5);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet1->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => $transactionDate,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet2->id,
        'asset_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 100,
        'date' => $transactionDate,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 110,
        'date' => now(),
    ]);

    $widget = livewire(SingleSecurityValuationChartWidget::class, [
        'record' => $security,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->not->toBeEmpty()
        ->and($data['labels'])->not->toBeEmpty();
});
