<?php

use App\Domains\Portfolio\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\SingleSecurityGainStatsOverview;
use App\Domains\Security\Filament\Widgets\SingleSecurityPerformanceStatsOverview;
use App\Domains\Security\Filament\Widgets\SingleSecurityPriceChartWidget;
use App\Domains\Security\Filament\Widgets\SingleSecurityValuationChartWidget;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('renders stats and chart widgets on the PEA edit page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id, 'user_id' => $user->id]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $peaWallet->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPerformanceStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityGainStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class)
        ->assertSeeLivewire(SingleSecurityPriceChartWidget::class);
});

it('renders stats and chart widgets on the CTO edit page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id, 'user_id' => $user->id]);
    $ctoWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'CTO']);

    livewire(EditWalletSecurity::class, ['record' => $security->id, 'walletId' => $ctoWallet->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPerformanceStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityGainStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class)
        ->assertSeeLivewire(SingleSecurityPriceChartWidget::class);
});

it('computes single security gain data correctly on edit page', function () {
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

    $widget = livewire(SingleSecurityGainStatsOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ]);
    $widget->assertOk();

    $data = $widget->instance()->getGainData();

    // Valuation = 10 * 120 = 1200
    // Invested = 10 * 100 + 5 = 1005
    // Plus-value = 1200 - 1005 = 195
    expect($data['plusValue'])->toContain('195')
        ->and($data['plusValuePositive'])->toBeTrue()
        ->and($data['fees'])->toContain('5');
});

it('only counts transactions of the correct account type', function () {
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

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 20,
        'unit_price' => 200,
        'fees' => 10,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(SingleSecurityGainStatsOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    // Only PEA: qty=10, price=100, fees=5
    // Valuation = 10 * 120 = 1200, Invested = 1005, PV = 195
    expect($data['plusValue'])->toContain('195')
        ->and($data['fees'])->toContain('5');
});

it('displays PRU on the edit page gain data', function () {
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

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 150,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => $user->id, 'name' => 'PEA']);

    $widget = livewire(SingleSecurityGainStatsOverview::class, [
        'record' => $security,
        'walletId' => $peaWallet->id,
    ]);

    $data = $widget->instance()->getGainData();

    // PRU = (10*100 + 10*200) / (10+10) = 3000/20 = 150
    expect($data['pru'])->toContain('150');
});

it('renders edit page without errors when security has no transactions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    livewire(EditWalletSecurity::class, ['record' => $security->id])
        ->assertOk();
});
