<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('ouvre la dernière année de transactions et laisse les précédentes repliées', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    /** Une seconde année d'historique : sans elle, rien ne distingue un groupe ouvert d'une liste plate. */
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 5,
        'unit_price' => 60,
        'date' => '2025-06-04',
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Transactions (2)')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-transaction-year]')).map(el => el.dataset.transactionYear).join('|')",
            '2026|2025',
        )
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->click('[data-transaction-year="2025"]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 2)
        ->click('[data-transaction-year="2026"]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|performance|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});

it('cache le détail d\'une transaction derrière un clic sur sa ligne', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 0)
        ->click('[data-transaction-row]')
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 1)
        ->assertScript("document.querySelector('[data-transaction-detail]').textContent.includes('10 × 80,00')", true)
        ->assertScript("document.querySelector('[data-transaction-detail]').textContent.includes('frais')", true)
        ->click('[data-transaction-row]')
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('détaille le montant de chaque secteur quand l\'instrument est détenu', function () {
    // Deux secteurs de poids distincts (60/40) sur une valeur de marché de 1 000 € : deux
    // montants différents l'un de l'autre et de la valeur totale, pour discriminer un mutant qui
    // afficherait la valeur brute ou intervertirait les poids.
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Technology, 'weight' => 0.6]);
    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Healthcare, 'weight' => 0.4]);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-sector-amount]')).map(el => el.textContent.replace(/\\s/g, ' ')).join('|')",
            '600 €|400 €',
        )
        ->assertNoJavaScriptErrors();
});

it('affiche uniquement la part sectorielle quand l\'instrument n\'est pas détenu', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Technology, 'weight' => 0.6]);
    SectorAllocation::factory()->create(['asset_id' => $instrument->id, 'sector' => Sector::Healthcare, 'weight' => 0.4]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-sector-amount]').length", 0)
        ->assertScript("document.querySelectorAll('[data-sector-share]').length", 2)
        ->assertNoJavaScriptErrors();
});

it('aligne le graphe de valorisation sur la marge du reste de la page, y compris sur mobile', function () {
    // Le graphe débordait de la marge : rendu bord à bord sur mobile, il commençait avant les
    // titres et finissait après eux. Les deux écarts sont mesurés, pas seulement celui de gauche.
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $paddingGaps = "(() => {
        const chart = document.querySelector('[data-section=\"valuation\"] [data-chart]').getBoundingClientRect();
        const heading = document.querySelector('[data-section=\"performance\"] h2').getBoundingClientRect();

        return [Math.round(chart.left - heading.left), Math.round(chart.right - heading.right)].join('|');
    })()";

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Performance par période')
        ->assertScript($paddingGaps, '0|0')
        ->assertNoJavaScriptErrors();
});

it('affiche les dividendes perçus quand l\'instrument en verse', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    /**
     * Recule la transaction par le constructeur de requêtes : il ne déclenche pas
     * `TransactionObserver`, donc `holdings_projection` garde la position déjà projetée par la
     * fixture (10 titres à 80 €) — seule la date d'ancienneté de la position change, pas sa
     * quantité. Une date relative, comme dans `DashboardTest`, pour que le test ne dépende pas de
     * la date d'exécution.
     */
    Transaction::query()->where('asset_id', $instrument->id)->update(['date' => now()->subYears(2)->format('Y-m-d')]);

    /**
     * Deux détachements de part et d'autre de la borne des douze mois, avec des montants par
     * action distincts : le total cumule les deux, le perçu à douze mois n'en garde qu'un, ce qui
     * rend `data-dividend-total` et `data-dividend-last12` discriminants l'un de l'autre. Des
     * dates relatives, comme dans `DashboardTest`, pour que le test reste vrai au-delà de 2027.
     */
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => now()->subMonths(15)->format('Y-m-d'),
        'amount_per_share' => 0.3,
    ]);
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => now()->subMonths(2)->format('Y-m-d'),
        'amount_per_share' => 0.5,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Dividendes (2)')
        ->assertScript("document.querySelectorAll('[data-dividend-row]').length", 2)
        // `includes` et non une égalité : `Intl` sépare le montant du symbole par une espace
        // insécable étroite, invisible dans le source du test mais fatale à une comparaison stricte.
        // Le total (8,00 €) cumule les deux détachements ; le perçu à douze mois (5,00 €) ne garde
        // que celui d'il y a deux mois — les deux valeurs diffèrent, donc un gabarit qui les
        // intervertirait serait pris en défaut.
        ->assertScript("document.querySelector('[data-dividend-total]').textContent.includes('8,00')", true)
        ->assertScript("document.querySelector('[data-dividend-last12]').textContent.includes('5,00')", true)
        // Rendement calculé sur le seul détachement de la fenêtre des douze mois (5,00 € rapportés
        // à un coût de 800 €), pas sur le total des deux.
        ->assertScript("document.querySelector('[data-dividend-yield]').textContent.includes('0,6')", true)
        // Les reçus se rendent du plus récent au plus ancien : la première ligne du tableau est
        // celle du détachement le plus récent. Trois cellules distinctes l'une de l'autre, pour
        // qu'une interversion de colonnes tombe : le montant par action, la quantité détenue et le
        // montant perçu ne se ressemblent pas.
        ->assertScript("document.querySelector('[data-dividend-per-share]').textContent.includes('0,50')", true)
        ->assertScript("document.querySelector('[data-dividend-quantity]').textContent.trim()", '10')
        ->assertScript("document.querySelector('[data-dividend-amount]').textContent.includes('5,00')", true)
        ->assertNoJavaScriptErrors();
});

it('n\'affiche aucune section dividendes sur un instrument capitalisant', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertDontSee('Dividendes')
        ->assertScript("document.querySelectorAll('[data-section=\"dividends\"]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('annonce le revenu attendu sur les douze prochains mois', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    Transaction::query()->where('asset_id', $instrument->id)->update(['date' => now()->subYears(2)->format('Y-m-d')]);

    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => now()->subMonths(2)->format('Y-m-d'),
        'amount_per_share' => 0.5,
    ]);

    /**
     * Renfort postérieur au détachement : la position passe à 20 titres sans rien percevoir de
     * plus. Le perçu reste sur 10 titres (5,00 €), l'estimation porte sur 20 (10,00 €) — deux
     * valeurs distinctes, donc un gabarit qui les intervertirait tomberait.
     */
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::query()->where('user_id', $user->id)->value('id'),
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'unit_price' => 90,
        'date' => now()->subMonth()->format('Y-m-d'),
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelector('[data-dividend-last12]').textContent.includes('5,00')", true)
        ->assertScript("document.querySelector('[data-dividend-estimate]').textContent.includes('10,00')", true)
        ->assertNoJavaScriptErrors();
});

it('n\'annonce aucun revenu attendu quand le dernier détachement date de plus de douze mois', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => now()->subMonths(15)->format('Y-m-d'),
        'amount_per_share' => 0.3,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-dividend-estimate]').length", 0)
        ->assertNoJavaScriptErrors();
});
