<?php

use App\Filament\Widgets\Dashboard\PortfolioAllocationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('returns labels matching account types and correct valuations', function () {
    $securityPea = Security::factory()->create();
    $securityCto = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $securityPea->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $securityCto->id,
        'quantity' => 5,
        'unit_price' => 200,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityPea->id,
        'date' => now(),
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityCto->id,
        'date' => now(),
        'close' => 250,
    ]);

    $widget = livewire(PortfolioAllocationChartWidget::class);
    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toBe(['PEA', 'CTO'])
        ->and($data['datasets'][0]['data'][0])->toBe(1200.0)
        ->and($data['datasets'][0]['data'][1])->toBe(1250.0);
});

it('returns zero valuations when no data exists', function () {
    $data = invade(livewire(PortfolioAllocationChartWidget::class)->instance())->getData();

    expect($data['labels'])->toBe(['PEA', 'CTO'])
        ->and($data['datasets'][0]['data'])->toBe([0.0, 0.0]);
});

it('only includes accounts with securities that have prices', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    // No price created for this security

    $data = invade(livewire(PortfolioAllocationChartWidget::class)->instance())->getData();

    expect($data['datasets'][0]['data'][0])->toBe(0.0)
        ->and($data['datasets'][0]['data'][1])->toBe(0.0);
});
