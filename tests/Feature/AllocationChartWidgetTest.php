<?php

use App\Filament\Pages\WalletPage;
use App\Filament\Widgets\Securities\AllocationChartWidget;
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
        ->assertSeeLivewire(AllocationChartWidget::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);
    $ctoWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'CTO']);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->assertOk()
        ->assertSeeLivewire(AllocationChartWidget::class);
});

it('returns labels and percentages per security', function () {
    $securityA = Security::factory()->create(['name' => 'Action A']);
    $securityB = Security::factory()->create(['name' => 'Action B']);

    Transaction::factory()->pea()->create([
        'security_id' => $securityA->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $securityB->id,
        'quantity' => 5,
        'unit_price' => 200,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityA->id,
        'date' => now(),
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityB->id,
        'date' => now(),
        'close' => 250,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(AllocationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toContain('Action A', 'Action B')
        ->and($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(2);

    $indexA = array_search('Action A', $data['labels']);
    $indexB = array_search('Action B', $data['labels']);

    // Action A: 10 * 120 = 1200, Action B: 5 * 250 = 1250, total = 2450
    expect($data['datasets'][0]['data'][$indexA])->toBe(49.0)
        ->and($data['datasets'][0]['data'][$indexB])->toBe(51.0);
});

it('excludes securities with no latest price', function () {
    $security = Security::factory()->create(['name' => 'No Price']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(AllocationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toBeEmpty()
        ->and($data['datasets'][0]['data'])->toBeEmpty();
});

it('returns empty data when no securities exist', function () {
    $peaWallet = Wallet::factory()->pea()->create();

    $widget = livewire(AllocationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toBeEmpty()
        ->and($data['datasets'][0]['data'])->toBeEmpty();
});
