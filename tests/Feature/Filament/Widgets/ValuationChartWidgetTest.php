<?php

use App\Filament\Pages\WalletPage;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;

use function Pest\Livewire\livewire;

it('can render on the PEA list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->assertOk()
        ->assertSeeLivewire(ValuationChartWidget::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);
    $ctoWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'CTO']);

    livewire(WalletPage::class, ['walletId' => $ctoWallet->id])
        ->assertOk()
        ->assertSeeLivewire(ValuationChartWidget::class);
});

it('defaults to total mode with aggregated valuation', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2024-01-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-15',
        'close' => 105,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-02-15',
        'close' => 110,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    // Total mode: Valorisation + Investi + Frais = 3 datasets
    expect($data['datasets'])->toHaveCount(3)
        ->and($data['labels'])->toHaveCount(2)
        ->and($data['labels'][0])->toBe('2024-01-15')
        ->and($data['labels'][1])->toBe('2024-02-15')
        ->and($data['datasets'][0]['label'])->toBe('Valorisation')
        ->and($data['datasets'][0]['stack'])->toBe('total')
        ->and($data['datasets'][0]['data'][0])->toBe(1050.0)
        ->and($data['datasets'][0]['data'][1])->toBe(1100.0)
        ->and($data['datasets'][1]['label'])->toBe('Investi')
        ->and($data['datasets'][2]['label'])->toBe('Frais')
        ->and($data['datasets'][2]['hidden'])->toBeTrue();
});

it('shows stacked areas per security in per_security mode', function () {
    $securityA = Security::factory()->create(['name' => 'ETF World']);
    $securityB = Security::factory()->create(['name' => 'ETF SP500']);

    Transaction::factory()->pea()->create([
        'security_id' => $securityA->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2024-01-15',
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $securityB->id,
        'quantity' => 5,
        'unit_price' => 200,
        'date' => '2024-01-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityA->id,
        'date' => '2024-01-15',
        'close' => 105,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityB->id,
        'date' => '2024-01-15',
        'close' => 210,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->set('filters.mode', 'per_security');

    $data = invade($widget->instance())->getData();

    // 2 security areas + Investi + Frais = 4 datasets
    $securityDatasets = collect($data['datasets'])->filter(fn ($d) => ($d['fill'] ?? false) === true);
    expect($securityDatasets)->toHaveCount(2);

    $worldDataset = collect($data['datasets'])->firstWhere('label', 'ETF World');
    $sp500Dataset = collect($data['datasets'])->firstWhere('label', 'ETF SP500');

    expect($worldDataset['data'][0])->toBe(1050.0)
        ->and($worldDataset['fill'])->toBeTrue()
        ->and($sp500Dataset['data'][0])->toBe(1050.0)
        ->and($sp500Dataset['fill'])->toBeTrue();
});

it('computes cumulative fees from transactions', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
        'date' => '2024-01-15',
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 110,
        'fees' => 3,
        'date' => '2024-02-10',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-15',
        'close' => 105,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-02-15',
        'close' => 110,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['datasets'][2]['label'])->toBe('Frais')
        ->and($data['datasets'][2]['data'][0])->toBe(5.0)
        ->and($data['datasets'][2]['data'][1])->toBe(8.0)
        ->and($data['datasets'][2]['hidden'])->toBeTrue();
});

it('excludes prices before the first transaction date', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2024-06-01',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-01',
        'close' => 50,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-06-03',
        'close' => 105,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'][0])->toBe(1050.0);
});

it('invested reflects mid-week transactions in the same week', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2024-01-10',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-12',
        'close' => 105,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['datasets'][1]['data'][0])->toBe(1000.0)
        ->and($data['datasets'][0]['data'][0])->toBe(1050.0);
});

it('extrapolates missing prices using the last known close in total mode', function () {
    $securityA = Security::factory()->create();
    $securityB = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $securityA->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2024-01-10',
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $securityB->id,
        'quantity' => 5,
        'unit_price' => 200,
        'date' => '2024-01-10',
    ]);

    // Both have prices on day 1
    SecurityPrice::factory()->create([
        'security_id' => $securityA->id,
        'date' => '2024-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityB->id,
        'date' => '2024-01-15',
        'close' => 200,
    ]);

    // Only securityA has a price on day 2
    SecurityPrice::factory()->create([
        'security_id' => $securityA->id,
        'date' => '2024-01-16',
        'close' => 110,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $data = invade($widget->instance())->getData();

    // Day 1: 10*100 + 5*200 = 2000
    // Day 2: 10*110 + 5*200 (extrapolated) = 2100
    expect($data['labels'])->toHaveCount(2)
        ->and($data['datasets'][0]['data'][0])->toBe(2000.0)
        ->and($data['datasets'][0]['data'][1])->toBe(2100.0);
});

it('extrapolates missing prices using the last known close in per_security mode', function () {
    $securityA = Security::factory()->create(['name' => 'ETF A']);
    $securityB = Security::factory()->create(['name' => 'ETF B']);

    Transaction::factory()->pea()->create([
        'security_id' => $securityA->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2024-01-10',
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $securityB->id,
        'quantity' => 5,
        'unit_price' => 200,
        'date' => '2024-01-10',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityA->id,
        'date' => '2024-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityB->id,
        'date' => '2024-01-15',
        'close' => 200,
    ]);

    // Only securityA has a price on day 2
    SecurityPrice::factory()->create([
        'security_id' => $securityA->id,
        'date' => '2024-01-16',
        'close' => 110,
    ]);

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->set('filters.mode', 'per_security');

    $data = invade($widget->instance())->getData();

    $etfADataset = collect($data['datasets'])->firstWhere('label', 'ETF A');
    $etfBDataset = collect($data['datasets'])->firstWhere('label', 'ETF B');

    // Day 2: ETF B should use last known price (200)
    expect($etfADataset['data'][0])->toBe(1000.0)
        ->and($etfADataset['data'][1])->toBe(1100.0)
        ->and($etfBDataset['data'][0])->toBe(1000.0)
        ->and($etfBDataset['data'][1])->toBe(1000.0);
});

it('returns empty data when no securities exist', function () {
    $peaWallet = Wallet::factory()->pea()->create();

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});
