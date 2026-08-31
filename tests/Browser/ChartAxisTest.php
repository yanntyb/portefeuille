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
        const svg = document.querySelector("[data-section='.$section.'] [data-chart] svg");

        return Array.from(svg.querySelectorAll("text"))
            .filter(label => label.getAttribute("text-anchor") === "end")
            .map(label => label.textContent.replace(/\s/g, " "))
            .join("|");
    })()';
}

it('ne chiffre que le minimum et le maximum sur le graphe de la page Actions', function () {
    // Position de 10 titres : valeur 1 000 €, investi 800 €. L'axe couvre donc 800 à 1 000 €.
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertVisible('[data-section=evolution] [data-chart] svg')
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

    visit("/asset/{$instrument->id}")
        ->assertSee('Cours')
        ->assertScript(yAxisLabels('price-history'), '90,00 €|110,00 €')
        ->assertNoJavaScriptErrors();
});

/**
 * Historique en deux paliers : cours à 1 000 € sur la première moitié, oscillant entre 100 et 200 €
 * sur la seconde. Sur plus d'un an la fenêtre d'ouverture ne montre que la seconde moitié, en
 * dessous elle montre tout : les deux cas donnent des extrêmes différents sur les mêmes données.
 *
 * Prix de revient à 150 €, entre les deux cours du palier bas : l'axe couvre la valeur et
 * l'investi, un investi hors de la bande volerait le minimum aux cours qu'on veut lire.
 */
function steppedHistoryFixture(int $days): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME']);

    foreach (range(0, $days) as $offset) {
        Price::factory()->create([
            'asset_id' => $instrument->id,
            'date' => now()->subDays($days - $offset)->format('Y-m-d'),
            'close' => $offset < $days / 2 ? 1000 : ($offset % 2 === 0 ? 100 : 200),
        ]);
    }

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => 150,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'unit_price' => 150,
        'date' => now()->subDays($days)->format('Y-m-d'),
    ]);

    return ['user' => $user, 'instrument' => $instrument];
}

it('chiffre les extrêmes de la fenêtre montrée, non ceux de tout l\'historique', function () {
    // Plus de deux ans : la fenêtre d'ouverture s'arrête à la dernière année, sous le palier haut.
    ['user' => $user] = steppedHistoryFixture(800);

    $this->actingAs($user);

    visit('/actions')
        ->assertVisible('[data-section=evolution] [data-chart] svg')
        ->assertScript(yAxisLabels('evolution'), '100 €|200 €')
        ->assertNoJavaScriptErrors();
});

it('remonte au palier haut dès que la fenêtre couvre tout l\'historique', function () {
    // Moins d'un an : le plancher de zoom montre l'historique entier, palier haut compris.
    ['user' => $user] = steppedHistoryFixture(300);

    $this->actingAs($user);

    visit('/actions')
        ->assertVisible('[data-section=evolution] [data-chart] svg')
        ->assertScript(yAxisLabels('evolution'), '100 €|1 000 €')
        ->assertNoJavaScriptErrors();
});

it('ne chiffre qu\'une fois un cours qui ne bouge pas', function () {
    // Minimum et maximum confondus : les écrire deux fois au même endroit les superposerait.
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-05-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertSee('Cours')
        ->assertScript(yAxisLabels('price-history'), '100,00 €')
        ->assertNoJavaScriptErrors();
});

it('ne chiffre que le minimum et le maximum sur le graphe du parc immobilier', function () {
    // Un bien estimé 150 000 € le jour même : la série vaut 0 avant cette estimation, elle seule.
    ['user' => $user] = propertyFixture();

    $this->actingAs($user);

    visit('/properties')
        ->assertScript(yAxisLabels('real-estate-evolution'), '0 €|150 000 €')
        ->assertNoJavaScriptErrors();
});
