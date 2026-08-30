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

it('replie toutes les années de transactions et les ouvre une à une', function () {
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

    $page = visit("/asset/{$instrument->id}")
        ->assertSee('Transactions')
        /** Repliée, la section ne montre que son titre : pas même la liste des années. */
        ->assertScript("document.querySelectorAll('[data-transaction-year]').length", 0);

    openSection($page, 'transactions')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-transaction-year]')).map(el => el.dataset.transactionYear).join('|')",
            '2026|2025',
        )
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->click('[data-transaction-year="2026"]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->click('[data-transaction-year="2025"]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 2)
        ->click('[data-transaction-year="2026"]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|figures|transactions|performance|sectors',
        )
        ->assertNoJavaScriptErrors();
});

it('cache le détail d\'une transaction derrière un clic sur sa ligne', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    openSection(visit("/asset/{$instrument->id}"), 'transactions')
        ->click('[data-transaction-year="2026"]')
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 0)
        /**
         * La quantité se lit sur la ligne repliée, seul le prix unitaire attend le clic. Le sens de
         * l'opération passe par la couleur, doublé d'un `aria-label` pour qui ne la perçoit pas.
         */
        ->assertScript("document.querySelector('[data-transaction-row]').textContent.includes('Achat')", false)
        ->assertScript("document.querySelector('[data-transaction-row]').getAttribute('aria-label')", 'Achat 10')
        ->click('[data-transaction-row]')
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 1)
        ->assertScript("document.querySelector('[data-transaction-detail]').textContent.includes('80,00')", true)
        ->click('[data-transaction-row]')
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('ne teinte que la quantité d\'une transaction, jamais son montant', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    /** Une vente en regard de l'achat de la fixture : les deux sens doivent tomber sur le même verdict. */
    Transaction::factory()->sell()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'unit_price' => 95,
        'date' => '2026-03-03',
    ]);

    $this->actingAs($user);

    /**
     * La couleur du montant se compare à celle de la date, teintée en `muted-foreground` : deux
     * quantités de teintes distinctes, et deux montants qui ne portent ni l'une ni l'autre.
     */
    openSection(visit("/asset/{$instrument->id}"), 'transactions')
        ->click('[data-transaction-year="2026"]')
        ->assertScript("document.querySelectorAll('[data-transaction-quantity]').length", 2)
        ->assertScript(
            "(() => { const [a, b] = Array.from(document.querySelectorAll('[data-transaction-quantity]'))"
            .'  .map(cell => getComputedStyle(cell).color);'
            .'  return a !== b; })()',
            true,
        )
        /** Le solde de l'année suit la règle des lignes : signé, mais jamais teinté. */
        ->assertScript(
            "(() => { const quantities = Array.from(document.querySelectorAll('[data-transaction-quantity]'))"
            .'  .map(cell => getComputedStyle(cell).color);'
            ."  return Array.from(document.querySelectorAll('[data-transaction-amount], [data-transaction-year-net]'))"
            .'  .every(cell => !quantities.includes(getComputedStyle(cell).color)); })()',
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('affiche les frais sur la ligne de transaction, sans clic', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    Transaction::query()->where('asset_id', $instrument->id)->update(['fees' => 3.5]);

    /** Une seconde ligne sans frais : sans elle, rien ne distingue un montant omis d'un zéro affiché. */
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 5,
        'unit_price' => 60,
        'fees' => 0,
        'date' => '2026-02-02',
    ]);

    $this->actingAs($user);

    openSection(visit("/asset/{$instrument->id}"), 'transactions')
        ->click('[data-transaction-year="2026"]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 2)
        ->assertScript("document.querySelectorAll('[data-transaction-detail]').length", 0)
        ->assertScript("document.querySelectorAll('[data-transaction-fees]').length", 1)
        /**
         * Les montants des deux lignes commencent à la même abscisse, alors qu'une seule porte des
         * frais : la colonne vide tient sa place au lieu de laisser glisser la suivante.
         */
        ->assertScript(
            "(() => { const [a, b] = Array.from(document.querySelectorAll('[data-transaction-row]'))"
            .'  .map(row => row.lastElementChild.getBoundingClientRect().left);'
            .'  return a === b; })()',
            true,
        )
        /** `toLocaleString` sépare le montant du symbole par une espace insécable étroite, d'où le remplacement. */
        ->assertScript("document.querySelector('[data-transaction-fees]').textContent.trim().replace(/\\s/g, ' ')", 'frais 3,50 €')
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

    openSection(visit("/asset/{$instrument->id}")->assertSeeIn('[data-section="sectors"] h2', 'Secteurs'), 'sectors')
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

    openSection(visit("/asset/{$instrument->id}"), 'sectors')
        ->assertScript("document.querySelectorAll('[data-sector-amount]').length", 0)
        ->assertScript("document.querySelectorAll('[data-sector-share]').length", 2)
        ->assertNoJavaScriptErrors();
});

it('aligne le graphe de valorisation sur la marge du reste de la page, y compris sur mobile', function () {
    // Le graphe débordait de la marge : rendu bord à bord sur mobile, il commençait avant les
    // titres et finissait après eux. Les deux écarts sont mesurés, pas seulement celui de gauche.
    // Le repère est la bascule de la section, et non son `h2` : le titre s'ajuste désormais à son
    // texte au sein d'un flex, sa boîte ne touche plus la marge droite.
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $paddingGaps = "(() => {
        const chart = document.querySelector('[data-section=\"valuation\"] [data-chart]').getBoundingClientRect();
        const heading = document.querySelector('[data-section=\"performance\"] [data-section-toggle]').getBoundingClientRect();

        return [Math.round(chart.left - heading.left), Math.round(chart.right - heading.right)].join('|');
    })()";

    visit("/asset/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Performances')
        ->assertScript($paddingGaps, '0|0')
        ->assertNoJavaScriptErrors();
});

it('donne une ligne à chaque repère de l\'en-tête, montant sur le bord droit', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    /** Chaque repère commence sous le précédent et pousse son montant contre le bord droit. */
    $stacked = "Array.from(document.querySelectorAll('[data-hero-meta] > span')).every((entry, index, entries) => {
        const row = entry.getBoundingClientRect();
        const value = entry.querySelector('strong')?.getBoundingClientRect() ?? null;
        const previous = index === 0 ? null : entries[index - 1].getBoundingClientRect();

        return (value === null || Math.abs(value.right - row.right) <= 1)
            && (previous === null || row.top >= previous.bottom);
    })";

    visit("/asset/{$instrument->id}")->on()->iPhone14Pro()
        ->assertScript("document.querySelectorAll('[data-hero-meta] > span').length", 4)
        ->assertScript($stacked, true)
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
     * action distincts : ils tombent dans deux groupes d'années distincts. Des dates relatives,
     * comme dans `DashboardTest`, pour que le test reste vrai au-delà de 2027.
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

    /**
     * Treize mois séparent les deux détachements : ils tombent toujours sur deux années civiles
     * distinctes, quelle que soit la date d'exécution — donc sur deux groupes repliés.
     */
    $recentYear = now()->subMonths(2)->format('Y');
    $olderYear = now()->subMonths(15)->format('Y');

    // Titre nu : le nombre de détachements se lit dans la liste dépliée, pas dans l'en-tête.
    openSection(visit("/asset/{$instrument->id}")->assertSee('Dividendes')->assertDontSee('Dividendes (2)'), 'dividends')
        // Les dividendes se lisent avant la répartition sectorielle : ce que l'actif rapporte
        // passe devant sa composition. Ordre relatif seul, les autres sections du gabarit
        // dépendant de la fixture.
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).filter(section => ['dividends', 'sectors'].includes(section)).join('|')",
            'dividends|sectors',
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-dividend-year]')).map(el => el.dataset.dividendYear).join('|')",
            "{$recentYear}|{$olderYear}",
        )
        // Toutes les années repliées à l'ouverture de la section : aucune ligne avant le clic sur
        // un groupe. Déplié, le groupe récent montre sa seule ligne, détail par action caché.
        ->assertScript("document.querySelectorAll('[data-dividend-row]').length", 0)
        ->click('[data-dividend-year="'.$recentYear.'"]')
        ->assertScript("document.querySelectorAll('[data-dividend-row]').length", 1)
        ->assertScript("document.querySelector('[data-dividend-amount]').textContent.includes('5,00')", true)
        ->assertScript("document.querySelectorAll('[data-dividend-detail]').length", 0)
        ->click('[data-dividend-row]')
        // Deux valeurs distinctes l'une de l'autre dans le détail, pour qu'une interversion tombe :
        // la quantité détenue et le montant par action ne se ressemblent pas.
        ->assertScript("document.querySelector('[data-dividend-quantity]').textContent.trim()", '10')
        ->assertScript("document.querySelector('[data-dividend-per-share]').textContent.includes('0,50')", true)
        ->click('[data-dividend-year="'.$olderYear.'"]')
        ->assertScript("document.querySelectorAll('[data-dividend-row]').length", 2)
        ->assertNoJavaScriptErrors();
});

it('n\'affiche aucune section dividendes sur un instrument capitalisant', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertDontSee('Dividendes')
        ->assertScript("document.querySelectorAll('[data-section=\"dividends\"]').length", 0)
        ->assertNoJavaScriptErrors();
});

/**
 * Nombre de pastilles teintées de la couleur du gain dans le graphe de valorisation : les repères
 * de détachement. Les deux teintes sont acceptées, le thème du navigateur de test décidant laquelle
 * la palette rend.
 */
function dividendMarkers(): string
{
    return '(() => {
        const gains = ["#00915d", "#34d399"];
        const svg = document.querySelector("[data-section=\'valuation\'] [data-chart] svg");

        return Array.from(svg.querySelectorAll("path")).filter(
            (node) => gains.includes((node.getAttribute("fill") || "").toLowerCase()),
        ).length;
    })()';
}

it('pointe d\'une pastille chaque détachement sur le graphe de valorisation', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    /** Position vieille de deux ans : la série de valorisation couvre alors le détachement. */
    Transaction::query()
        ->where('asset_id', $instrument->id)
        ->update(['date' => now()->subYears(2)->format('Y-m-d')]);

    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => now()->subMonths(3)->format('Y-m-d'),
        'amount_per_share' => 0.5,
    ]);

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertVisible('[data-section="valuation"] [data-chart] svg')
        ->assertScript(dividendMarkers(), 1)
        ->assertNoJavaScriptErrors();
});
