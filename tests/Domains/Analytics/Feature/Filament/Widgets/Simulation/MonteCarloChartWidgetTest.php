<?php

use App\Domains\Analytics\Filament\Widgets\Simulation\MonteCarloChartWidget;

use function Pest\Livewire\livewire;

it('returns 4 datasets', function () {
    $user = \App\Domains\User\Models\User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(MonteCarloChartWidget::class)->instance();
    $data = invade($widget)->getData();

    expect($data['datasets'])->toHaveCount(4)
        ->and($data['datasets'][0]['label'])->toContain('P90')
        ->and($data['datasets'][1]['label'])->toContain('P50')
        ->and($data['datasets'][2]['label'])->toContain('P10')
        ->and($data['datasets'][3]['label'])->toContain('Capital investi');
});

it('returns labels from Année 0 to Année N', function () {
    $user = \App\Domains\User\Models\User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(MonteCarloChartWidget::class)->instance();
    $data = invade($widget)->getData();

    expect($data['labels'])->toHaveCount(21)
        ->and($data['labels'][0])->toBe('Année 0')
        ->and($data['labels'][20])->toBe('Année 20');
});

it('capital investi dataset is deterministic', function () {
    $user = \App\Domains\User\Models\User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(MonteCarloChartWidget::class)
        ->set('capitalInitial', 10000)
        ->set('versementMensuel', 500)
        ->instance();

    $data = invade($widget)->getData();
    $capitalInvesti = $data['datasets'][3]['data'];

    expect((int) $capitalInvesti[0])->toBe(10000)
        ->and((int) $capitalInvesti[1])->toBe(16000);
});

it('respects duree prop for labels count', function () {
    $user = \App\Domains\User\Models\User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(MonteCarloChartWidget::class)
        ->set('duree', 10)
        ->instance();

    $data = invade($widget)->getData();

    expect($data['labels'])->toHaveCount(11);
});

it('updates settings on onSettingsUpdated call', function () {
    $user = \App\Domains\User\Models\User::factory()->create();
    $this->actingAs($user);

    $widget = livewire(MonteCarloChartWidget::class)->instance();

    expect((int) $widget->versementMensuel)->toBe(500);

    $widget->onSettingsUpdated(
        versementMensuel: 750,
        tauxMoyen: 8,
        volatilite: 12,
        nbSimulations: 1000,
    );

    expect((int) $widget->versementMensuel)->toBe(750)
        ->and((int) $widget->tauxMoyen)->toBe(8)
        ->and((int) $widget->volatilite)->toBe(12)
        ->and((int) $widget->nbSimulations)->toBe(1000);
});
