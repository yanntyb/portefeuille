<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\PerformanceStatsOverview;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

it('displays performance stats with correct values', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(PerformanceStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $stats = $widget->instance()->getPerformanceData();

    // 3 mois: Valo début = 10 * 100 = 1000, Valo fin = 10 * 120 = 1200 → +20%
    $threeMonths = collect($stats)->firstWhere('label', '3 mois');
    expect($threeMonths['value'])->toBe('+20.00 %')
        ->and($threeMonths['color'])->toBe('success');
});

it('displays dash for periods without data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'date' => '2025-05-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(PerformanceStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $stats = $widget->instance()->getPerformanceData();

    // 1 an: pas de position à cette date → dash
    $oneYear = collect($stats)->firstWhere('label', '1 an');
    expect($oneYear['value'])->toBe('—')
        ->and($oneYear['color'])->toBe('gray');
});

it('shows danger color for negative returns', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 80,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(PerformanceStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $stats = $widget->instance()->getPerformanceData();

    $threeMonths = collect($stats)->firstWhere('label', '3 mois');
    expect($threeMonths['value'])->toBe('-20.00 %')
        ->and($threeMonths['color'])->toBe('danger');
});

it('returns seven period stats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'asset_id' => $security->id,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(PerformanceStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $stats = $widget->instance()->getPerformanceData();

    expect($stats)->toHaveCount(7);
});
