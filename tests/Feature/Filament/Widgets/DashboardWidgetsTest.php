<?php

use App\Enums\Sector;
use App\Filament\Widgets\Dashboard\DashboardPerformanceStatsOverview;
use App\Filament\Widgets\Dashboard\DashboardSectorAllocationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

it('aggregates sector data from all accounts', function () {
    $securityPea = Security::factory()->create(['name' => 'ETF PEA']);
    $securityCto = Security::factory()->create(['name' => 'ETF CTO']);

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
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityCto->id,
        'date' => now(),
        'close' => 200,
    ]);

    // ETF PEA: valuation = 10 * 100 = 1000
    SecuritySector::factory()->create([
        'security_id' => $securityPea->id,
        'sector' => Sector::Technology,
        'weight' => 0.6,
    ]);

    // ETF CTO: valuation = 5 * 200 = 1000
    SecuritySector::factory()->create([
        'security_id' => $securityCto->id,
        'sector' => Sector::Healthcare,
        'weight' => 0.5,
    ]);

    $widget = livewire(DashboardSectorAllocationChartWidget::class);
    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    // Technology = 1000*0.6 = 600, Healthcare = 1000*0.5 = 500
    // Grand total = 1100
    expect($data['labels'])->toHaveCount(2)
        ->and($data['datasets'])->toHaveCount(2);

    $indexTech = array_search('Technologie', $data['labels']);
    $indexHealth = array_search('Santé', $data['labels']);

    $peaDataset = collect($data['datasets'])->firstWhere('label', 'ETF PEA');
    $ctoDataset = collect($data['datasets'])->firstWhere('label', 'ETF CTO');

    // ETF PEA: Tech = 600/1100*100 = 54.5%, Health = 0%
    expect($peaDataset['data'][$indexTech])->toBe(54.5)
        ->and($peaDataset['data'][$indexHealth])->toBe(0.0);

    // ETF CTO: Tech = 0%, Health = 500/1100*100 = 45.5%
    expect($ctoDataset['data'][$indexTech])->toBe(0.0)
        ->and($ctoDataset['data'][$indexHealth])->toBe(45.5);
});

it('returns empty sector data when no securities exist', function () {
    $data = invade(livewire(DashboardSectorAllocationChartWidget::class)->instance())->getData();

    expect($data['labels'])->toBeEmpty()
        ->and($data['datasets'])->toBeEmpty();
});

it('displays performance stats across all accounts', function () {
    Carbon::setTestNow('2025-06-15');

    $securityPea = Security::factory()->create();
    $securityCto = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $securityPea->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $securityCto->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityPea->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityPea->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityCto->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityCto->id,
        'date' => '2025-06-15',
        'close' => 110,
    ]);

    $widget = livewire(DashboardPerformanceStatsOverview::class);
    $widget->assertOk();

    $stats = $widget->instance()->getPerformanceData();

    expect($stats)->toHaveCount(7);

    // 3 mois: PEA valo = 10*120=1200, CTO valo = 10*110=1100, total = 2300
    // Début: PEA = 10*100=1000, CTO = 10*100=1000, total = 2000
    // Rendement = (2300-2000)/2000 = +15%
    $threeMonths = collect($stats)->firstWhere('label', '3 mois');
    expect($threeMonths['value'])->toBe('+15.00 %')
        ->and($threeMonths['color'])->toBe('success');
});

it('returns seven period stats for performance widget', function () {
    $widget = livewire(DashboardPerformanceStatsOverview::class);

    $stats = $widget->instance()->getPerformanceData();

    expect($stats)->toHaveCount(7);
});
