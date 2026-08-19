<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * Étiquettes de l'axe des valeurs, dans l'ordre croissant où ECharts les pose. Les étiquettes de cet
 * axe sont les seuls textes alignés à droite du SVG : celles du temps sont centrées sous la leur.
 */
function yAxisLabels(string $section): string
{
    return '(() => {
        const svg = document.querySelector("[data-section=' . $section . '] [data-chart] svg");

        return Array.from(svg.querySelectorAll("text"))
            .filter(label => label.getAttribute("text-anchor") === "end")
            .map(label => label.textContent.replace(/\s/g, " "))
            .join("|");
    })()';
}

it('ne chiffre que le minimum et le maximum sur le graphe du tableau de bord', function () {
    // Position de 10 titres : valeur 1 000 €, investi 800 €. L'axe couvre donc 800 à 1 000 €.
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Évolution')
        ->assertScript(yAxisLabels('evolution'), '800 €|1 000 €')
        ->assertNoJavaScriptErrors();
});

it('ne chiffre que le minimum et le maximum sur le cours d\'un instrument', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-05-01', 'close' => 90]);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-06-01', 'close' => 110]);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Cours')
        ->assertScript(yAxisLabels('price-history'), '90,00 €|110,00 €')
        ->assertNoJavaScriptErrors();
});

it('rechiffre les deux extrêmes quand la fenêtre de zoom change', function () {
    // Cours à 1 000 € sur la première moitié de l'historique, à 100 € sur la seconde : la fenêtre
    // d'ouverture, qui ne montre que la dernière année, ne voit pas le palier haut.
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME']);
    $days = 800;

    foreach (range(0, $days) as $offset) {
        Price::factory()->create([
            'asset_id' => $instrument->id,
            'date' => now()->subDays($days - $offset)->format('Y-m-d'),
            'close' => $offset < 400 ? 1000 : 100,
        ]);
    }

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => 10,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'unit_price' => 10,
        'date' => now()->subDays($days)->format('Y-m-d'),
    ]);

    $this->actingAs($user);

    $page = visit('/');
    $page->assertSee('Évolution')->assertScript(yAxisLabels('evolution'), '10 €|100 €');

    foreach (range(1, 10) as $ignored) {
        scrollChart($page, 'evolution', -400);
    }

    $page->assertScript(yAxisLabels('evolution'), '10 €|1 000 €')
        ->assertNoJavaScriptErrors();
});
