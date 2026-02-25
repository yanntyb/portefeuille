<?php

use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('can render on the PEA list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(AllocationChartWidget::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(ListCtoSecurities::class)
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

    $widget = livewire(AllocationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
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

    $widget = livewire(AllocationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toBeEmpty()
        ->and($data['datasets'][0]['data'])->toBeEmpty();
});

it('returns empty data when no securities exist', function () {
    $widget = livewire(AllocationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toBeEmpty()
        ->and($data['datasets'][0]['data'])->toBeEmpty();
});
