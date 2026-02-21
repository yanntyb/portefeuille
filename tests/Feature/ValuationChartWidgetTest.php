<?php

use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('can render on the PEA list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(ValuationChartWidget::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(ListCtoSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(ValuationChartWidget::class);
});

it('computes valuation from transactions and prices', function () {
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

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['datasets'])->toHaveCount(2)
        ->and($data['labels'])->toHaveCount(2)
        ->and($data['labels'][0])->toBe('2024-01-15')
        ->and($data['labels'][1])->toBe('2024-02-15')
        ->and($data['datasets'][0]['label'])->toBe('Valorisation')
        ->and($data['datasets'][0]['data'])->each->toBeGreaterThan(0)
        ->and($data['datasets'][1]['label'])->toBe('Investi')
        ->and($data['datasets'][1]['data'])->each->toBeGreaterThan(0);
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

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'][0])->toBe(1050.0);
});

it('invested reflects mid-week transactions in the same week', function () {
    $security = Security::factory()->create();

    // Transaction on Wednesday 2024-01-10 (week starts Monday 2024-01-08)
    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2024-01-10',
    ]);

    // Price on Friday of the same week
    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-12',
        'close' => 105,
    ]);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $data = invade($widget->instance())->getData();

    // Invested should be 1000 (10 * 100) in the same week as the transaction
    expect($data['datasets'][1]['data'][0])->toBe(1000.0)
        ->and($data['datasets'][0]['data'][0])->toBe(1050.0);
});

it('returns empty data when no securities exist', function () {
    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});
