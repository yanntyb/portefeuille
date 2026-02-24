<?php

use App\Enums\AccountType;
use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Widgets\Securities\SingleSecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('renders stats and chart widgets on the PEA edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class);
});

it('renders stats and chart widgets on the CTO edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(EditCtoSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class);
});

it('computes single security stats correctly on edit page', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    // Pass the plain record (like Filament does after Livewire hydration)
    $widget = livewire(SingleSecurityStatsOverview::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);

    $widget->assertOk();

    $stats = invade($widget->instance())->getStats();

    // Valuation = 10 * 120 = 1200
    // Invested = 10 * 100 + 5 = 1005
    // Plus-value = 1200 - 1005 = 195
    // PRU = 1000 / 10 = 100
    // Fees = 5
    expect($stats)->toHaveCount(4)
        ->and($stats[0]->getLabel())->toBe('Valorisation')
        ->and($stats[0]->getValue())->toContain('1,200')
        ->and($stats[1]->getLabel())->toBe('Plus-value')
        ->and($stats[1]->getValue())->toContain('195')
        ->and($stats[2]->getLabel())->toBe('PRU')
        ->and($stats[2]->getValue())->toContain('100')
        ->and($stats[3]->getLabel())->toBe('Frais')
        ->and($stats[3]->getValue())->toContain('5');
});

it('only counts transactions of the correct account type', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 20,
        'unit_price' => 200,
        'fees' => 10,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $widget = livewire(SingleSecurityStatsOverview::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);

    $stats = invade($widget->instance())->getStats();

    // Only PEA transaction: qty=10, unit_price=100, fees=5
    // Valuation = 10 * 120 = 1200
    expect($stats[0]->getValue())->toContain('1,200');
});

it('renders edit page without errors when security has no transactions', function () {
    $security = Security::factory()->create();

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk();
});
