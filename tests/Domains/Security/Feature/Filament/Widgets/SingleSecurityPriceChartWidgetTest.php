<?php

use App\Domains\Security\Filament\Widgets\SingleSecurityPriceChartWidget;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns empty when no record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(SingleSecurityPriceChartWidget::class)->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});

it('returns empty when no prices exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    $widget = livewire(SingleSecurityPriceChartWidget::class, [
        'record' => $security,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});

it('returns prices in chronological order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 100,
        'date' => now()->subDays(2),
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 110,
        'date' => now()->subDays(1),
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 120,
        'date' => now(),
    ]);

    $widget = livewire(SingleSecurityPriceChartWidget::class, [
        'record' => $security,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['labels'])->toHaveCount(3);
});

it('maps close prices to dataset data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 100.5,
        'date' => now()->subDays(1),
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 110.75,
        'date' => now(),
    ]);

    $widget = livewire(SingleSecurityPriceChartWidget::class, [
        'record' => $security,
    ])->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'][0]['data'])->toContain(100.5, 110.75);
});

it('heading shows latest price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 150,
        'date' => now(),
    ]);

    $heading = livewire(SingleSecurityPriceChartWidget::class, [
        'record' => $security,
    ])
        ->instance()
        ->getHeading();

    expect($heading)->toContain('150');
});

it('heading shows default text when no price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    $heading = livewire(SingleSecurityPriceChartWidget::class, [
        'record' => $security,
    ])
        ->instance()
        ->getHeading();

    expect($heading)->toBe('Évolution du prix');
});
