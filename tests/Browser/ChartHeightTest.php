<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;

/** Hauteur peinte du premier graphe de la page, arrondie au pixel. */
function chartHeight(): string
{
    return "Math.round(document.querySelector('[data-chart]').getBoundingClientRect().height)";
}

it('donne au graphe de la fiche instrument la hauteur de celui du tableau de bord', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $dashboard = visit('/')->assertSee('Évolution');
    $detail = visit("/instruments/{$instrument->id}")->assertSee('ACME');

    expect($detail->script(chartHeight()))
        ->toBe($dashboard->script(chartHeight()))
        ->toBe(240);

    $dashboard->assertNoJavaScriptErrors();
    $detail->assertNoJavaScriptErrors();
});

it('garde les deux graphes à la même hauteur compacte sur mobile', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $dashboard = visit('/')->on()->iPhone14Pro()->assertSee('Évolution');
    $detail = visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()->assertSee('ACME');

    expect($detail->script(chartHeight()))
        ->toBe($dashboard->script(chartHeight()))
        ->toBe(170);

    $dashboard->assertNoJavaScriptErrors();
    $detail->assertNoJavaScriptErrors();
});

it('donne la même hauteur au cours d\'un instrument non détenu', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->actingAs($user);

    $detail = visit("/instruments/{$instrument->id}")->assertSee('Cours');

    expect($detail->script(chartHeight()))->toBe(240);

    $detail->assertNoJavaScriptErrors();
});
