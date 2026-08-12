# Décomposition des performances par période en tableau

Date : 2026-08-12
Statut : design validé (brainstorming). Fait évoluer les widgets livrés par
`2026-07-12-dashboard-portfolio-performances-design.md`.

## Context

Les performances par période s'affichent aujourd'hui comme une ligne de petites cartes
scrollables horizontalement (`label` + `%`), dupliquée à l'identique sur deux pages :
`Dashboard.vue:174-191` (portefeuille) et `Instruments/Show.vue:250-261` (un titre). Les deux
consomment le même DTO `PerformanceData{key, label, pct}`, produit par
`ValuationCalculator::trailingPerformances()` via `BuildPortfolioPerformances` /
`BuildAssetPerformances`.

Problème : `returnOverWindow()` (`ValuationCalculator.php:175-200`) calcule cinq grandeurs
(date de début effective, valeur au début, apports nets, P&L en euros, pourcentage) et n'en
expose qu'une. Le pourcentage seul est abstrait : « +12,4 % » ne dit ni sur combien, ni si la
valeur a bougé parce que le marché a monté ou parce qu'on a versé.

On remplace la ligne de cartes par un **tableau : une ligne par période, une colonne par
grandeur**, et on rend le composant unique pour les deux pages.

## Décisions (validées)

- **Une ligne par période**, colonnes `Période | Depuis | Valeur début | Apports | Gain | Perf.`
- La **valeur de fin n'est pas une colonne** : identique sur toutes les lignes, et déjà
  affichée au-dessus (« Valorisation » sur le dashboard, « Gain / perte » sur la page titre).
- **Composant Vue unique** `PerformanceTable.vue` utilisé par les deux pages ; la duplication
  de markup disparaît.
- **Mobile : scroll horizontal du tableau**, toutes les colonnes partout. C'est déjà le
  comportement des cartes (`-mx-6 overflow-x-auto px-6`), on garde le même wrapper.
- **Les périodes non couvertes par l'historique disparaissent** au lieu d'afficher des tirets.
  Un portefeuille de deux mois ne montre plus de ligne « 6 mois » vide.
- Conséquence directe : `PerformanceData` n'a plus **aucun champ nullable**, et le front n'a
  plus de cas `—` à gérer pour les performances.

Hors périmètre : pas de sélecteur de période sur le tableau, pas de tri des colonnes, pas de
performance annualisée, pas de croisement période × titre.

## Architecture

### Backend — `app/Contexts/Valuation`

Nouveau DTO interne au contexte, retour enrichi de la fenêtre de calcul :

```php
// app/Contexts/Valuation/Datas/PerformanceWindowData.php
readonly class PerformanceWindowData
{
    public function __construct(
        public string $startDate,     // label réel du dernier point <= borne, 'Y-m-d'
        public float $valueStart,
        public float $contributions,  // apports nets sur la fenêtre
        public float $pnl,            // gain € hors apports
        public float $pct,
    ) {}
}
```

`ValuationCalculator::returnOverWindow(ValuationSeriesData $daily, string $boundary):
?PerformanceWindowData` — le corps du calcul ne change pas, seules les valeurs intermédiaires
déjà présentes (`$startIndex`, `$valueStart`, `$contributions`, `$pnl`) sont retournées au lieu
d'être jetées. Les deux cas `null` sont inchangés : aucun point ≤ `$boundary`, ou
`$valueStart <= 0`.

`trailingPerformances()` : chaque période appelle `returnOverWindow()` et **n'émet une
`PerformanceData` que si la fenêtre n'est pas null**. La boucle des années pleines reste telle
quelle (elle borne déjà sur `$firstDay`). YTD, 1/3/6 mois passent du « toujours émis, pct
éventuellement null » à « émis seulement si couvert ».

```php
readonly class PerformanceData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $startDate,
        public float $valueStart,
        public float $contributions,
        public float $gain,
        public float $pct,
    ) {}
}
```

Un portefeuille trop jeune pour toute période (moins d'un mois, créé après le 1er janvier)
renvoie `[]`. Les deux pages gardent déjà un garde `v-if="performances.length"` : rien à
changer sur ce chemin.

`BuildPortfolioPerformances` et `BuildAssetPerformances` sont inchangés — ils délèguent
entièrement à `trailingPerformances()`.

### Frontend

**Nouveau `resources/js/lib/format.ts`.** `eur()`, `pct()` et `gainClass()` sont dupliqués mot
pour mot entre `Dashboard.vue:112-125` et `Show.vue:90-96,165-170`. Le composant partagé en a
besoin : on les extrait à côté de `chart.ts`. Nuance à conserver : le dashboard formate en
`maximumFractionDigits: 0`, la page titre en `2` → signature `eur(value, digits = 2)`, le
dashboard passe `0` explicitement. Les deux pages importent depuis `lib/format.ts` et
suppriment leurs copies locales.

**Nouveau `resources/js/components/PerformanceTable.vue`.** Prop unique
`performances: Performance[]`. Rend un `ui/table` dans le wrapper scrollable existant
(`-mx-6 overflow-x-auto px-6`), sans état interne.

| Colonne | Source | Format |
|---|---|---|
| Période | `label` | texte, aligné à gauche |
| Depuis | `startDate` | date `fr-FR`, aligné à droite |
| Valeur début | `valueStart` | `eur()`, aligné à droite |
| Apports | `contributions` | `eur()` signé, aligné à droite |
| Gain | `gain` | `eur()` signé + `gainClass()`, aligné à droite |
| Perf. | `pct` | `pct()` + `gainClass()`, aligné à droite |

`Dashboard.vue` : le bloc `174-191` devient `<PerformanceTable :performances="performances" />`
sous le titre « Performance par période » + `PerformanceInfoDialog`. Le fallback `Deferred`
(`166-172`, six blocs carrés de 64×52) devient un skeleton de cinq lignes pulsées pleine
largeur, cohérent avec la forme du tableau.

`Instruments/Show.vue` : le bloc `250-261` devient le même appel de composant.

`PerformanceInfoDialog` variant `periods` : la `DialogDescription` dit « Les petites cases
YTD, 1 mois, … 4 ans » — formulation à reprendre pour parler de lignes, et le texte doit
mentionner que les colonnes Apports et Gain sont les deux termes du numérateur de la formule
déjà affichée (`(valeur fin - valeur début - versements) / valeur début`). Le contenu exact
est rédigé à l'implémentation ; l'assertion `assertSee('cumulés, pas annualisés')` du test
navigateur doit rester vraie.

## Tests

**Backend (Pest).**

- `ValuationCalculatorTest.php:269-303` : les quatre assertions `returnOverWindow(...)` passent
  de `->toBe(20.0)` à `->pct`. Les deux cas null restent des `toBeNull()`.
- Nouveau cas sur une fenêtre contenant un apport : vérifie `startDate`, `valueStart`,
  `contributions`, `pnl` et la cohérence `pnl / valueStart * 100 === pct`.
- `ValuationCalculatorTest.php:314` : le cas nominal de `trailingPerformances()` assert les
  nouveaux champs.
- Nouveau cas : série de deux mois ⇒ le résultat ne contient **ni `3M` ni `6M`**.
- `BuildPortfolioPerformancesTest` / `BuildAssetPerformancesTest` : forme des `PerformanceData`
  retournées (nouvelles clés présentes, aucune nulle).
- `tests/Feature/DashboardPageTest.php` et `tests/Feature/InstrumentDetailPageTest.php` : le
  prop `performances` porte les nouvelles clés.

**Frontend.**

- `bun run build` doit passer (les deux pages changent d'imports).
- `tests/Browser/DashboardPerformanceInfoTest.php` : vérifier que les assertions ciblent bien
  du texte (`Performance par période`, `aria-label` du dialogue) et non le markup des cartes ;
  ajouter une assertion sur un en-tête du tableau (p. ex. `Apports`).
- Contrôle Playwright `/` et `/instruments/{id}` en desktop et mobile : tableau lisible,
  scroll horizontal effectif sur mobile, aucune ligne à valeurs vides, pas d'erreur JS.
