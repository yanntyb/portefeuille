<?php

use App\Enums\AccountType;
use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Widgets\Securities\SingleSecurityGainStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
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
        ->assertSeeLivewire(SingleSecurityPerformanceStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityGainStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class)
        ->assertSeeLivewire(SingleSecurityPriceChartWidget::class);
});

it('renders stats and chart widgets on the CTO edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(EditCtoSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPerformanceStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityGainStatsOverview::class)
        ->assertSeeLivewire(SingleSecurityValuationChartWidget::class)
        ->assertSeeLivewire(SingleSecurityPriceChartWidget::class);
});

it('computes single security gain data correctly on edit page', function () {
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

    $widget = livewire(SingleSecurityGainStatsOverview::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
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

    $widget = livewire(SingleSecurityGainStatsOverview::class, [
        'record' => $security,
        'accountType' => AccountType::Pea->value,
    ]);

    $data = $widget->instance()->getGainData();

    // Only PEA: qty=10, price=100, fees=5
    // Valuation = 10 * 120 = 1200, Invested = 1005, PV = 195
    expect($data['plusValue'])->toContain('195')
        ->and($data['fees'])->toContain('5');
});

it('renders edit page without errors when security has no transactions', function () {
    $security = Security::factory()->create();

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk();
});
