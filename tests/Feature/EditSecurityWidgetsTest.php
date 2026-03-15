<?php

use App\Enums\AccountType;
use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Widgets\Securities\SingleSecurityFeesStatsWidget;
use App\Filament\Widgets\Securities\SingleSecurityPlusValueWidget;
use App\Filament\Widgets\Securities\SingleSecurityPriceStatsWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationStatsWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('renders stats and chart widgets on the PEA edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPlusValueWidget::class)
        ->assertSeeLivewire(SingleSecurityValuationStatsWidget::class)
        ->assertSeeLivewire(SingleSecurityFeesStatsWidget::class)
        ->assertSeeLivewire(SingleSecurityPriceStatsWidget::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class);
});

it('renders stats and chart widgets on the CTO edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(EditCtoSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPlusValueWidget::class)
        ->assertSeeLivewire(SingleSecurityValuationStatsWidget::class)
        ->assertSeeLivewire(SingleSecurityFeesStatsWidget::class)
        ->assertSeeLivewire(SingleSecurityPriceStatsWidget::class)
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

    // Test plus-value widget
    $plusValueWidget = livewire(SingleSecurityPlusValueWidget::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);
    $plusValueWidget->assertOk();
    $plusValueStats = invade($plusValueWidget->instance())->getStats();

    // Valuation = 10 * 120 = 1200
    // Invested = 10 * 100 + 5 = 1005
    // Plus-value = 1200 - 1005 = 195
    expect($plusValueStats)->toHaveCount(2)
        ->and($plusValueStats[0]->getLabel())->toBe('Plus-value latente')
        ->and($plusValueStats[0]->getValue())->toContain('195')
        ->and($plusValueStats[1]->getLabel())->toBe('Plus-value réalisée');

    // Test valuation widget
    $valuationWidget = livewire(SingleSecurityValuationStatsWidget::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);
    $valuationWidget->assertOk();
    $valuationStats = invade($valuationWidget->instance())->getStats();

    expect($valuationStats)->toHaveCount(1)
        ->and($valuationStats[0]->getLabel())->toBe('Valorisation')
        ->and($valuationStats[0]->getValue())->toContain('1');

    // Test fees widget
    $feesWidget = livewire(SingleSecurityFeesStatsWidget::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);
    $feesWidget->assertOk();
    $feesStats = invade($feesWidget->instance())->getStats();

    expect($feesStats)->toHaveCount(1)
        ->and($feesStats[0]->getLabel())->toBe('Frais')
        ->and($feesStats[0]->getValue())->toContain('5');

    // Test price widget
    $priceWidget = livewire(SingleSecurityPriceStatsWidget::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);
    $priceWidget->assertOk();
    $priceStats = invade($priceWidget->instance())->getStats();

    // PRU = 1000 / 10 = 100
    expect($priceStats)->toHaveCount(2)
        ->and($priceStats[0]->getLabel())->toBe('Prix actuel')
        ->and($priceStats[1]->getLabel())->toBe('PRU')
        ->and($priceStats[1]->getValue())->toContain('100');
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

    $widget = livewire(SingleSecurityValuationStatsWidget::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);

    $stats = invade($widget->instance())->getStats();

    // Only PEA transaction: qty=10, unit_price=100, fees=5
    // Valuation = 10 * 120 = 1200
    expect($stats)->toHaveCount(1)
        ->and($stats[0]->getLabel())->toBe('Valorisation')
        ->and($stats[0]->getValue())->toContain('1');
});

it('renders edit page without errors when security has no transactions', function () {
    $security = Security::factory()->create();

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk();
});
