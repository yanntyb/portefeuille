<?php

use App\Enums\Sector;
use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('can render on the PEA list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(SectorAllocationChartWidget::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(ListCtoSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(SectorAllocationChartWidget::class);
});

it('aggregates sector data weighted by valuation for account list', function () {
    $securityA = Security::factory()->create(['name' => 'ETF World']);
    $securityB = Security::factory()->create(['name' => 'ETF Tech']);

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
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityB->id,
        'date' => now(),
        'close' => 200,
    ]);

    // ETF World: valuation = 10 * 100 = 1000
    SecuritySector::factory()->create([
        'security_id' => $securityA->id,
        'sector' => Sector::Technology,
        'weight' => 0.3,
    ]);

    SecuritySector::factory()->create([
        'security_id' => $securityA->id,
        'sector' => Sector::Healthcare,
        'weight' => 0.2,
    ]);

    // ETF Tech: valuation = 5 * 200 = 1000
    SecuritySector::factory()->create([
        'security_id' => $securityB->id,
        'sector' => Sector::Technology,
        'weight' => 0.8,
    ]);

    $widget = livewire(SectorAllocationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    // Technology = 1000*0.3 (World) + 1000*0.8 (Tech) = 1100 total
    // Healthcare = 1000*0.2 (World only) = 200 total
    // Grand total = 1300
    expect($data['labels'])->toHaveCount(2)
        ->and($data['datasets'])->toHaveCount(2);

    $indexTech = array_search('Technologie', $data['labels']);
    $indexHealth = array_search('Santé', $data['labels']);

    // Dataset 0 = ETF World, Dataset 1 = ETF Tech (values as % of grand total 1300)
    $worldDataset = collect($data['datasets'])->firstWhere('label', 'ETF World');
    $techDataset = collect($data['datasets'])->firstWhere('label', 'ETF Tech');

    expect($worldDataset['data'][$indexTech])->toBe(23.1)
        ->and($worldDataset['data'][$indexHealth])->toBe(15.4)
        ->and($techDataset['data'][$indexTech])->toBe(61.5)
        ->and($techDataset['data'][$indexHealth])->toBe(0.0);
});

it('shows sector weights as percentages for a single security', function () {
    $security = Security::factory()->create();

    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'sector' => Sector::Technology,
        'weight' => 0.6,
    ]);

    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'sector' => Sector::Healthcare,
        'weight' => 0.4,
    ]);

    $widget = livewire(SectorAllocationChartWidget::class, [
        'record' => $security,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toContain('Technologie', 'Santé')
        ->and($data['datasets'][0]['data'])->toHaveCount(2);

    $indexTech = array_search('Technologie', $data['labels']);
    $indexHealth = array_search('Santé', $data['labels']);

    expect($data['datasets'][0]['data'][$indexTech])->toBe(60.0)
        ->and($data['datasets'][0]['data'][$indexHealth])->toBe(40.0);
});

it('returns empty data when no sectors exist', function () {
    $widget = livewire(SectorAllocationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    expect($data['labels'])->toBeEmpty()
        ->and($data['datasets'])->toBeEmpty();
});
