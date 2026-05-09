<?php

use App\Domains\Analytics\Filament\Widgets\Dashboard\DashboardGainStatsOverview;
use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Stock;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns empty collection when tablePageClass is null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(DashboardGainStatsOverview::class)->instance();
    $result = invade($widget)->getFilteredSecurities();

    expect($result)->toBeEmpty();
});

it('returns securities from page table query when tablePageClass is set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Stock::factory()->create();

    $transaction = \App\Domains\Portfolio\Models\Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
    ]);

    AssetPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 100,
    ]);

    $widget = livewire(DashboardGainStatsOverview::class, [
        'tablePageClass' => \App\Domains\Portfolio\Filament\Pages\WalletPage::class,
        'walletId' => $wallet->id,
    ])->instance();

    $result = invade($widget)->getFilteredSecurities();

    expect($result)->not->toBeEmpty()
        ->and($result->contains($security))->toBeTrue();
});

it('filters by shownSecurityIds when set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Stock::factory()->create();
    $security2 = Stock::factory()->create();

    \App\Domains\Portfolio\Models\Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security1->id,
    ]);

    \App\Domains\Portfolio\Models\Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security2->id,
    ]);

    AssetPrice::factory()->create(['asset_id' => $security1->id]);

    AssetPrice::factory()->create(['asset_id' => $security2->id]);

    $widget = livewire(DashboardGainStatsOverview::class, [
        'tablePageClass' => \App\Domains\Portfolio\Filament\Pages\WalletPage::class,
        'walletId' => $wallet->id,
    ])->instance();

    $widget->shownSecurityIds = [$security1->id];
    $result = invade($widget)->getFilteredSecurities();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($security1->id);
});

it('loads latestPrice relationship when withPrice is true', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Stock::factory()->create();

    \App\Domains\Portfolio\Models\Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
    ]);

    AssetPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 100,
    ]);

    $widget = livewire(DashboardGainStatsOverview::class, [
        'tablePageClass' => \App\Domains\Portfolio\Filament\Pages\WalletPage::class,
        'walletId' => $wallet->id,
    ])->instance();

    $result = invade($widget)->getFilteredSecurities(withPrice: true);

    expect($result->first()->relationLoaded('latestPrice'))->toBeTrue();
});

it('calls getFilteredSecurities with withPrice parameter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Stock::factory()->create();

    \App\Domains\Portfolio\Models\Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
    ]);

    AssetPrice::factory()->create([
        'asset_id' => $security->id,
        'close' => 100,
    ]);

    $widget = livewire(DashboardGainStatsOverview::class, [
        'tablePageClass' => \App\Domains\Portfolio\Filament\Pages\WalletPage::class,
        'walletId' => $wallet->id,
    ])->instance();

    $result1 = invade($widget)->getFilteredSecurities(withPrice: true);
    $result2 = invade($widget)->getFilteredSecurities(withPrice: false);

    expect($result1)->not->toBeEmpty()
        ->and($result2)->not->toBeEmpty();
});
