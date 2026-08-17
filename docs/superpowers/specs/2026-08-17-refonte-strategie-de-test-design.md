# Refonte de la stratégie de test

Date : 2026-08-17

## Problème

Une retouche visuelle d'une ligne coûte trois fichiers de test. Le commit `11737e1` déplace
deux lignes de Vue et modifie `DashboardCardlessSectionsTest`, `DashboardHoldingsTableTest` et
`DashboardSectionOrderTest`. Ce n'est pas de la malchance, c'est mécanique.

Mesures sur la suite actuelle :

- 45 fichiers de test, 4005 lignes
- 21 fichiers Browser, 79 tests, 126 appels `assertScript`
- 19 assertions de géométrie ou de style calculé (`getBoundingClientRect`, `getComputedStyle`)
- 18 assertions d'absence (`querySelectorAll(...).length === 0`)
- 25 fichiers répétant le même bloc de fixture, commentaire copié inclus
- `resources/js/lib/` : 649 lignes de TypeScript pur réparties en 11 modules, **zéro test**

Quatre causes :

1. **Des tests qui verrouillent la maquette, pas le comportement.** `'24|24|24|24'` d'écart entre
   sections, `backgroundColor` du body égal à celui de `main`, absence de sparkline, absence de
   légende, absence de period picker. Ces tests ne peuvent trouver aucun bug : la seule façon de
   les faire échouer est de changer volontairement le design.
2. **Des vérités assertées plusieurs fois.** `DashboardSectionOrderTest` et
   `DashboardCardlessSectionsTest` assertent la même chaîne `valuation|evolution|holdings|performances|sectors`.
   Les trois tests de `InstrumentPerformanceBarsTest` sont des copies mot pour mot de
   `DashboardPerformanceBarsTest`. Quatre tests de `InstrumentValuationChartTest` doublent
   `DashboardEvolutionZoomTest` et `DashboardEvolutionLayoutTest`.
3. **De l'arithmétique testée à travers un navigateur.** Le tri des positions, les poids, le top 10,
   le formatage en euros, les bornes de l'axe des valeurs, la fenêtre de zoom initiale, la recherche
   du catalogue : toute cette logique vit dans `resources/js/lib/` et dans des `computed` de
   composants, et se trouve vérifiée en grattant du SVG ECharts dans un vrai navigateur.
4. **Aucune fixture partagée.** Le bloc `User::query()->delete()` + user + wallet + instrument +
   deux prix + holding + transaction est recopié dans 25 fichiers.

## La règle

Cinq filtres, appliqués dans l'ordre à tout test existant ou nouveau.

### F0 — Où vit la vérité ?

| La vérité est… | Couche |
|---|---|
| une fonction pure ou un `computed` de composant — tri, poids, seuils, formatage, objet d'options ECharts | **Vitest** |
| une prop envoyée par le serveur, un calcul PHP, une autorisation, une redirection | **Pest Feature** |
| dépendante d'un vrai navigateur — CSS ou thème appliqué, rendu SVG réel, interaction native, réseau Inertia | **Pest Browser** |

Un test Browser qui n'a pas besoin d'un vrai navigateur est un test Vitest mal placé.

### F1 — Le test peut-il échouer sans qu'on ait volontairement changé la maquette ?

Non → supprimer. Ce n'est pas un test, c'est le procès-verbal d'une décision de design. Git garde
déjà la décision.

### F2 — La valeur attendue change-t-elle si je change la fixture ?

Oui → comportement, garder. Non → constante figée dans le markup, repasser par F1.

### F3 — Cette vérité est-elle déjà assertée ailleurs ?

Oui → une seule fois, dans le test dont c'est le sujet. Vaut aussi contre la couche Feature :
`tests/Feature` couvre déjà les props du dashboard, le drapeau `held` du catalogue, la position
masquée quand l'instrument n'est pas détenu et les quatre props différées. Ne pas les réasserter
en Browser.

### F4 — Sur quoi porte l'assertion ?

Autorisé : texte lu via un `data-*` nommé ; ordre ou comptage piloté par les données ; attribut
publié exprès pour le test (`data-zoom-window`) ; interaction réelle ; thème calculé.

Interdit : `getBoundingClientRect`, `getComputedStyle` pour du décor, classes Tailwind,
`length === 0` sur un élément qu'on a décidé de ne pas afficher.

Besoin de géométrie → une capture d'écran de référence, pas N assertions de pixels.

**Exception validée** : le thème calculé est du comportement, pas du décor. `SystemThemeTest`
garde le droit d'utiliser `getComputedStyle` pour vérifier qu'un fond suit le thème au lieu d'un
noir codé en dur — c'est une régression déjà survenue.

### F5 — Nommage

Le titre énonce un comportement avec sa dépendance aux données (« trie les positions du plus lourd
au plus léger »), jamais une décision de forme (« sans en-tête tabulaire »). Un titre en
« sans / pas de / free of » signale presque toujours un F1.

## Outillage

**Deux dépendances ajoutées** : `vitest`, `happy-dom`.

`happy-dom` n'est pas optionnel. `resources/js/lib/chart.ts:4` importe `isDark` depuis `theme.ts`,
qui appelle `usePreferredDark()` de `@vueuse/core` au chargement du module et touche donc
`window.matchMedia`. Sans environnement DOM, tout `import` de `chart.ts` échoue.

- **Configuration** : bloc `test` dans `vite.config.ts`, qui porte déjà l'alias `@`.
- **Scripts** : `test:js` → `vitest run`, `test:js:watch` → `vitest`.
- **Emplacement** : co-localisé, `resources/js/lib/format.test.ts` à côté de `format.ts`. Aucun
  nouveau dossier de base, `vue-tsc --noEmit` typecheck les tests gratuitement, et Vite les
  ignore puisque rien ne les importe.

Aucun composant n'est monté en test : `@vue/test-utils` n'est pas ajouté. La logique sort des
`.vue` vers `lib/` (voir plus bas), les composants ne gardent que le rendu et l'état d'interface.

Gain de pureté optionnel, hors périmètre : passer la palette en paramètre à
`buildValueVsInvestedOption` supprimerait l'import de `theme.ts` dans `chart.ts` et rendrait les
palettes claire et sombre testables explicitement.

## Extractions

### Déjà pur, il ne manque que les tests

| Module | Fonctions | Vérités reprises au navigateur |
|---|---|---|
| `format.ts` | `eur`, `signedEur`, `pct`, `signedPct`, `frDate`, `gainClass` | montants du hero, `1 000,00 €`, `+25,0 %`, `au 01/07/2026` |
| `catalog.ts` | `filterCatalog`, `joinTrends`, `isRangeKey` | recherche nom/ticker/ISIN, accents, retour vide, recomptage |
| `chart.ts` | `buildValueVsInvestedOption`, `buildPriceHistoryOption`, `sumPerAsset`, `lastYearWindow`, clamp `MIN_ZOOM_SPAN_MS` | axe non ancré à zéro, graduation en euros, aire en dégradé, point sur la dernière valeur, poignées sans dates, fenêtre 12 mois, historique plus court qu'un an |
| `instrument.ts` | `investedOf` | « Investi 800,00 € » |
| `layout.ts` | `pageContainer` | alignement du contenu sur le fil d'Ariane, aujourd'hui mesuré au pixel |

`lastYearWindow` (`chart.ts:284`) et `valueVsInvestedTooltip` (`chart.ts:209`) sont privés : il faut
les exporter.

### À extraire des composants vers `lib/`

**`lib/bars.ts`** — nouveau module, deux fonctions. La même arithmétique « barre relative à la plus
grande valeur du lot, pas au total » est recopiée dans `HoldingsList.vue:43`,
`SectorBreakdownList.vue:27` et `PerformanceBars.vue`. Trois copies, trois tests Browser identiques
intitulés « scales the widest bar to the full track ».

```ts
export const largestOf = (values: number[]): number
export const relativeBarWidth = (value: number, largest: number): string
```

**`lib/portfolio.ts`** — depuis `HoldingsList.vue:20-46` et `HoldingsSection.vue:6` :

```ts
export const holdingWeights = (holdings: HoldingLine[], limit?: number): HoldingWeight[]
```

Un point d'entrée unique qui enchaîne tri par valeur de marché → total sur **tout** le portefeuille
→ coupe à `limit` → part → largeur de barre → dégradé d'opacité. Capture l'invariant documenté en
commentaire à `HoldingsList.vue:23` : on ne coupe que le rendu, les agrégats restent calculés sur
l'ensemble. Invariant aujourd'hui gardé par un seul test navigateur.

**`lib/sector.ts`** — depuis `SectorBreakdownList.vue:12-27` :

```ts
export const collapsedSectors = (rows: SectorBreakdownRow[], expanded: boolean, collapsedCount = 6): { rows: SectorBreakdownRow[]; hiddenCount: number }
export const sectorRows = (weights: SectorWeight[], marketValue: number | null): SectorBreakdownRow[]
```

`collapsedSectors` couvre le repli au-delà du sixième, « tout visible s'il y en a six ou moins » et
le décompte caché. `sectorRows` couvre les montants par secteur quand l'instrument est détenu et la
part seule sinon.

**`lib/performance.ts`** — depuis `PerformanceBars.vue` :

```ts
export const performanceBars = (performances: Performance[]): PerformanceBar[]
```

Porte la largeur, la couleur et l'infobulle de chaque ligne.

**`lib/catalog.ts`** — depuis `CatalogHeader.vue` :

```ts
export const catalogCount = (rows: CatalogRow[]): string
```

Rend « 3 instruments · 1 détenu » et son recomptage sur une recherche.

**`lib/instrument.ts`** — depuis `HeroSection.vue` :

```ts
export const heroMeta = (instrument: Instrument, position: InstrumentPosition | null): string[]
```

Rend « Titres 10 · PRU 80,00 € · Investi 800,00 € · Cours 100,00 € » quand l'instrument est détenu,
« au 01/07/2026 » sinon.

### Assertions sur l'objet ECharts, pas sur le SVG

Le point le plus rentable de la refonte. « Axe non ancré à zéro », « graduation en euros », « aire
en dégradé », « point sur la dernière valeur », « poignées de zoom sans dates », « infobulle qui
énonce le gain », « valeur tracée contre l'investi » sont tous des champs de l'objet retourné par
`buildValueVsInvestedOption` et `buildPriceHistoryOption`. On les asserte sur l'objet, en
millisecondes, au lieu de filtrer `svg path[stroke="#5257d6"]` dans un navigateur.

Conséquence sur `tests/Pest.php` : `drawnLines` et `lowestValueAxisLabel` disparaissent,
`zoomWindowSpan` et `scrollChart` restent pour l'unique test de molette.

## Triage des 79 tests Browser

Les trois colonnes ne forment pas une partition : certains tests portent deux vérités, l'une
migrée et l'autre supprimée, ou l'une migrée et l'autre conservée. C'est pourquoi leur somme
dépasse le nombre de tests.

La colonne « supprimées ou fusionnées » compte deux cas distincts : 23 vérités réellement
supprimées (F1, F3, F4) et 4 vérités conservées mais fusionnées dans un test survivant — les trois
tests de zoom qui deviennent un seul parcours à la molette, et les trois tests de catalogue qui
deviennent un seul parcours recherche / tri / effacement.

| Fichier | Tests | Vérités → Vitest | Vérités supprimées ou fusionnées | Tests Browser conservés |
|---|--:|--:|--:|--:|
| `BreadcrumbTest` | 4 | 2 | 0 | 2 |
| `DashboardCardlessSectionsTest` | 1 | 0 | 1 | 0 |
| `DashboardChartLegendTest` | 1 | 1 | 0 | 0 |
| `DashboardEvolutionLayoutTest` | 8 | 5 | 3 | 0 |
| `DashboardEvolutionZoomTest` | 6 | 3 | 2 | 1 |
| `DashboardHoldingsTableTest` | 6 | 2 | 2 | 2 |
| `DashboardPerformanceBarsTest` | 3 | 3 | 0 | 0 |
| `DashboardPerformanceInfoTest` | 1 | 0 | 0 | 1 |
| `DashboardSectionOrderTest` | 1 | 0 | 0 | 1 |
| `DashboardSectionSpacingTest` | 1 | 0 | 1 | 0 |
| `DashboardSectorBreakdownTest` | 5 | 3 | 1 | 1 |
| `DashboardValuationHeadlineTest` | 2 | 2 | 0 | 0 |
| `InstrumentCatalogRadarTest` | 10 | 6 | 3 | 1 |
| `InstrumentHeroTest` | 3 | 2 | 1 | 0 |
| `InstrumentPerformanceBarsTest` | 3 | 0 | 3 | 0 |
| `InstrumentPrefetchTest` | 3 | 0 | 0 | 3 |
| `InstrumentSectionsTest` | 3 | 1 | 1 | 1 |
| `InstrumentSectorBreakdownTest` | 4 | 2 | 2 | 0 |
| `InstrumentTransactionsTest` | 2 | 0 | 1 | 1 |
| `InstrumentValuationChartTest` | 8 | 2 | 6 | 0 |
| `SystemThemeTest` | 4 | 1 | 0 | 3 |
| **Total** | **79** | **35** | **27** | **18** |

**Huit fichiers entièrement supprimés** : `DashboardCardlessSectionsTest`,
`DashboardSectionSpacingTest`, `DashboardChartLegendTest`, `DashboardEvolutionLayoutTest`,
`DashboardPerformanceBarsTest`, `InstrumentPerformanceBarsTest`, `DashboardValuationHeadlineTest`,
`InstrumentValuationChartTest`.

Les 35 vérités migrées deviennent 45 à 55 tests Vitest : un test navigateur agrégé se décompose en
plusieurs cas unitaires, et les fonctions déjà pures gagnent des cas limites que le navigateur ne
couvrait pas.

### Décisions notables

`InstrumentPerformanceBarsTest` disparaît en entier : ses trois tests sont des copies mot pour mot
de `DashboardPerformanceBarsTest`, mêmes sélecteurs, mêmes assertions, sur deux pages. Une
fonction `performanceBars()`, un test Vitest, les deux pages servies.

`InstrumentValuationChartTest` perd six tests sur huit : quatre doublent
`DashboardEvolutionZoomTest` et `DashboardEvolutionLayoutTest`, deux sont des F1 (« sans period
picker », « sans légende »).

Un seul test navigateur survit sur les graphes : « la molette zoome sans jamais descendre sous un
an et sans demander l'historique au serveur », fusion de trois tests. C'est le garde-fou qui
vérifie qu'ECharts honore réellement `minValueSpan`. Tout le reste est de l'arithmétique.

`DashboardSectionOrderTest` est conservé bien que F1 le condamne à la lettre : une assertion,
fusionnée dans le smoke du dashboard, où elle ne coûte rien, et l'ordre des sections porte du sens
produit.

## Suite Browser cible : 8 fichiers, 17 tests

Les 18 tests conservés sont réorganisés par page, pas repris tels quels. Deux fusions :
l'`aria-label` de la section Positions et le lien vers les instruments tiennent dans un seul test
de `DashboardTest` ; le repli des transactions et la bascule valorisation / historique de prix
tiennent dans un seul test de `InstrumentDetailTest`. `SmokeTest` porte un test par page, celui du
dashboard absorbant l'ordre des sections. Total : 17.

| Fichier | Tests | Pourquoi un vrai navigateur |
|---|--:|---|
| `PrefetchTest` | 3 | réseau Inertia au survol |
| `SystemThemeTest` | 3 | `matchMedia` et CSS appliqué |
| `SmokeTest` | 3 | un par page : charge, sections présentes et dans l'ordre, zéro erreur JS |
| `BreadcrumbTest` | 2 | navigation réelle, `aria-current` |
| `DashboardTest` | 3 | dialogue d'info (reka-ui), repli des secteurs, `aria-label` de la section Positions et lien vers les instruments |
| `EvolutionZoomTest` | 1 | molette réelle sur ECharts |
| `CatalogSearchTest` | 1 | frappe, tri de colonne (`@tanstack/vue-table`) et effacement, en un parcours |
| `InstrumentDetailTest` | 1 | repli des transactions, bascule valorisation / historique de prix |

De 79 à 17. Un changement de maquette touche désormais zéro ou un fichier de test, contre trois
aujourd'hui.

## Fixture partagée

Les fichiers Browser survivants partagent un seul helper, exposé dans `tests/Pest.php` à côté des
helpers de graphe existants :

```php
function portfolioFixture(array $overrides = []): array
```

Il rend `['user' => …, 'wallet' => …, 'instrument' => …]` et remplace le bloc recopié 25 fois.
`DashboardEvolutionLayoutTest` et `DashboardHoldingsTableTest` ont déjà des helpers locaux
(`userWithEvolution`, `seedHoldingLine`, `userWithDenseEvolution`) : ils fusionnent dans le helper
partagé.

## Correctif de la migration

`database/migrations/2026_02_22_141110_add_user_id_to_transactions_table.php:19` crée un
utilisateur `yanntyb.lbc@gmail.com` pour rattacher les transactions orphelines. Sous
`RefreshDatabase` les migrations tournent, donc chaque test démarre avec cet utilisateur en base —
d'où le `User::query()->delete()` recopié dans 25 fichiers avec son commentaire.

Correctif : créer l'utilisateur de repli seulement s'il y a réellement des transactions à
rattacher. Sur une base fraîche, `transactions` est vide et aucun utilisateur n'est semé.

```php
public function up(): void
{
    Schema::table('transactions', function (Blueprint $table) {
        $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
    });

    if (! DB::table('transactions')->whereNull('user_id')->exists()) {
        return;
    }

    $userId = DB::table('users')->where('email', 'yanntyb.lbc@gmail.com')->value('id')
        ?? DB::table('users')->insertGetId([/* charge utile inchangée */]);

    DB::table('transactions')->whereNull('user_id')->update(['user_id' => $userId]);
}
```

Modifier une migration déjà passée est sans effet en production : elle ne rejouera pas, la base a
déjà l'utilisateur et le rattachement. Le changement ne concerne que les bases fraîches, c'est-à-dire
les tests et les nouvelles installations.

Un test de non-régression le verrouille : sur une base migrée, `users` est vide.

## Hors périmètre

- **Monter des composants en test.** `@vue/test-utils` n'est pas ajouté. À reconsidérer seulement
  si un composant garde une logique d'état irréductible après extraction.
- **Captures d'écran de référence.** F4 les autorise en remplacement des assertions de géométrie,
  mais aucune n'est mise en place ici. Les vérités de géométrie supprimées le restent.
- **Injection de palette dans `chart.ts`.** Gain de pureté, pas requis.
- **Le mot de passe semé par la migration.** Le correctif ci-dessus supprime l'utilisateur des
  bases fraîches, ce qui règle le symptôme, mais une installation neuve **avec** des transactions
  orphelines créerait toujours un compte dont le mot de passe est `pass`
  (`add_user_id_to_transactions_table.php:22`). Ticket séparé.

## Séquencement

Cinq étapes, chacune committable seule et laissant la suite verte.

1. **Outillage.** `vitest` + `happy-dom`, bloc `test` dans `vite.config.ts`, scripts, un premier
   fichier `format.test.ts` qui prouve que la chaîne tourne.
2. **Extractions et tests Vitest, module par module.** Chaque module : extraire si besoin, écrire
   les tests, brancher le composant sur la nouvelle fonction. Ordre proposé par rentabilité —
   `format`, `bars`, `portfolio`, `sector`, `performance`, `catalog`, `instrument`, `chart`,
   `layout`. `chart.ts` est le plus gros morceau et le plus rentable.
3. **Réécriture de la suite Browser.** Supprimer les huit fichiers condamnés, réorganiser les
   survivants en huit fichiers par page.
4. **Fixture partagée.** `portfolioFixture()` dans `tests/Pest.php`, absorption des helpers locaux.
5. **Correctif de la migration.** Retrait des `User::query()->delete()` et test de non-régression.

L'étape 3 ne doit pas précéder l'étape 2 : on ne supprime un test navigateur qu'une fois sa vérité
couverte en Vitest, sinon la couverture tombe entre les deux commits.

## Critères de réussite

1. `bun run test:js` passe, avec 45 tests Vitest au minimum sur `resources/js/lib/`.
2. `php artisan test --compact` passe.
3. `tests/Browser/` compte au plus 8 fichiers et 20 tests.
4. Aucun `getBoundingClientRect` ni `getComputedStyle` dans `tests/Browser/`, sauf
   `SystemThemeTest`.
5. Aucune occurrence de `User::query()->delete()` dans `tests/`.
6. Aucun `assertScript` dont l'échec ne puisse venir que d'un changement de design volontaire.
7. Le temps de `php artisan test` est mesuré avant et après ; la baisse est constatée.
