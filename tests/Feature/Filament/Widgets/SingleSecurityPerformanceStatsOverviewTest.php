<?php

use App\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

it('can render on the edit security page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $peaWallet->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPerformanceStatsOverview::class);
});

it('computes returns for a single security', function () {
    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $stats = $widget->instance()->getPerformanceData();

    $threeMonths = collect($stats)->firstWhere('label', '3 mois');
    expect($threeMonths['value'])->toBe('+20.00 %')
        ->and($threeMonths['color'])->toBe('success');
});

it('returns seven period stats for a single security', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ]);

    $stats = $widget->instance()->getPerformanceData();

    expect($stats)->toHaveCount(7);
});

it('filters transactions by wallet', function () {
    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 5,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);
    $ctoWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'CTO']);

    $peaWidget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ]);

    $ctoWidget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'walletId' => $ctoWallet->id,
    ]);

    $peaStats = $peaWidget->instance()->getPerformanceData();
    $ctoStats = $ctoWidget->instance()->getPerformanceData();

    // Meme rendement car meme prix, mais les deux doivent fonctionner independamment
    $peaThreeMonths = collect($peaStats)->firstWhere('label', '3 mois');
    $ctoThreeMonths = collect($ctoStats)->firstWhere('label', '3 mois');

    expect($peaThreeMonths['value'])->toBe('+20.00 %')
        ->and($ctoThreeMonths['value'])->toBe('+20.00 %');
});
