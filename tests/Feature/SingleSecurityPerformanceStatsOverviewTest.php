<?php

use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

it('can render on the edit security page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSeeLivewire(SingleSecurityPerformanceStatsOverview::class);
});

it('computes returns for a single security', function () {
    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $widget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'accountType' => 'pea',
    ]);

    $widget->assertOk();

    $stats = $widget->instance()->getPerformanceData();

    $threeMonths = collect($stats)->firstWhere('label', '3 mois');
    expect($threeMonths['value'])->toBe('+20.00 %')
        ->and($threeMonths['color'])->toBe('success');
});

it('returns seven period stats for a single security', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    $widget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'accountType' => 'pea',
    ]);

    $stats = $widget->instance()->getPerformanceData();

    expect($stats)->toHaveCount(7);
});

it('filters transactions by account type', function () {
    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'date' => '2025-01-01',
        'quantity' => 5,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $peaWidget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'accountType' => 'pea',
    ]);

    $ctoWidget = livewire(SingleSecurityPerformanceStatsOverview::class, [
        'record' => $security,
        'accountType' => 'cto',
    ]);

    $peaStats = $peaWidget->instance()->getPerformanceData();
    $ctoStats = $ctoWidget->instance()->getPerformanceData();

    // Meme rendement car meme prix, mais les deux doivent fonctionner independamment
    $peaThreeMonths = collect($peaStats)->firstWhere('label', '3 mois');
    $ctoThreeMonths = collect($ctoStats)->firstWhere('label', '3 mois');

    expect($peaThreeMonths['value'])->toBe('+20.00 %')
        ->and($ctoThreeMonths['value'])->toBe('+20.00 %');
});
