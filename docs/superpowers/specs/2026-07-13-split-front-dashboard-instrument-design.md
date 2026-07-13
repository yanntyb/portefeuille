# Split front — Dashboard & Instrument en composants

**Date:** 2026-07-13
**Branche:** vendredi-soir

## Objectif

Simplifier le front. Deux pages monolithiques (`Dashboard.vue` ~382 l., `Instruments/Show.vue` ~402 l.) partagent beaucoup de code dupliqué. On veut : découper chaque page en sous-composants ciblés **et** dédupliquer le code partagé via composables + composants partagés.

## Problème actuel

Duplication entre `Dashboard.vue` et `Instruments/Show.vue` :

- Formatters `eur` / `pct` / `gainClass` (seule différence : `maximumFractionDigits` 0 vs 2).
- Constante `flatCard`.
- Types `RangeKey` / `GranularityKey`, tableaux `rangeOptions` / `granularityOptions`, gardes `isRangeKey` / `isGranularityKey`.
- Refs `selectedRange` / `selectedGranularity` / `reloading` + logique `reloadSeries` / `selectRange` / `selectGranularity` (diffèrent seulement par le tableau `only:` de `router.reload`).
- Markup des deux groupes de boutons toggle (range + granularité) — identique.
- Markup des chips de performance — identique.

Chaque page mélange aussi plusieurs sections indépendantes (résumé, graphes, tableaux) dans un seul fichier.

## Architecture cible

### Composables — `resources/js/composables/`

**`useMoneyFormat.ts`**
- Exporte `eur(value: number | null, digits = 0): string`, `pct(value: number | null): string`, `gainClass(value: number | null): string`.
- Dashboard appelle `eur(v)` (digits 0), Show appelle `eur(v, 2)`.
- Supprime les formatters dupliqués.

**`useValuationControls.ts`**
- Détient : types `RangeKey` / `GranularityKey`, `rangeOptions` / `granularityOptions`, gardes `isRangeKey` / `isGranularityKey`.
- `useValuationControls(only: string[], initialRange?, initialGranularity?)` retourne `selectedRange`, `selectedGranularity`, `reloading`, `selectRange`, `selectGranularity`.
- `only` : Dashboard `['valuationSeries', 'investedByAsset']`, Show `['valuation']`.
- Encapsule `router.reload` + toggle des refs.

### Composants partagés — `resources/js/components/shared/`

**`RangeGranularityToggle.vue`**
- Les deux groupes de boutons toggle.
- Props : `selectedRange: RangeKey`, `selectedGranularity: GranularityKey`, `rangeOptions`, `granularityOptions`.
- Emits : `select-range(key)`, `select-granularity(key)`.

**`PerformanceChips.vue`**
- La rangée de chips de performance (label + pct coloré).
- Props : `performances: { key; label; pct }[]`.
- Utilise `useMoneyFormat` pour `pct` / `gainClass`.

### Split Dashboard — `resources/js/components/dashboard/`

- `PortfolioSummary.vue` — bloc investi + gain/perte + `PerformanceChips` (dans `<Deferred data="performances">`), avec `PerformanceInfoDialog`.
- `ValuationEvolutionChart.vue` — card « Évolution » (area chart valeur vs investi, `<Deferred data="valuationSeries">`).
- `InvestedByAssetChart.vue` — card « Investi par titre » (line stepline, `<Deferred data="investedByAsset">`).
- `HoldingsTable.vue` — card « Positions » (table des lignes, liens `/instruments/{id}`).
- `AllocationDonut.vue` — card « Répartition » (donut par type).

`Dashboard.vue` devient mince : props + composables + layout `<main>` qui câble les enfants et gère `reloading`.

### Split Instrument — `resources/js/components/instrument/`

- `InstrumentHeader.vue` — header nom/ticker/type/isin/prix + lien retour.
- `PositionSummary.vue` — bloc gain/perte + `PerformanceChips`.
- `ValuationCharts.vue` — paire base 100 « Cours » + « Valeur vs Investi » (cas position, `<Deferred data="valuation">`).
- `PriceHistoryChart.vue` — card « Cours » 12 mois (cas sans position, `<Deferred data="priceHistory">`).
- `TransactionsTable.vue` — card « Transactions ».
- `SectorsList.vue` — card « Secteurs ».

`Show.vue` devient mince : props + composables + layout qui bascule position / sans-position.

### Types

- `RangeKey` / `GranularityKey` vivent dans `useValuationControls.ts` (source unique).
- Les interfaces spécifiques à un graphe/tableau (`HoldingLine`, `TransactionLine`, `SectorWeight`, séries…) restent colocalisées avec le composant qui les consomme, passées via props.
- Pas de fichier `types/` global sauf si une interface est réellement partagée entre plusieurs composants (`Performance` / `AssetPerformance` → props de `PerformanceChips`).

## Flux de données

- Les pages restent les seules à recevoir les props Inertia et à appeler les composables.
- Elles passent les données aux enfants via props ; les enfants sont « bêtes » (présentation).
- `<Deferred>` reste **dans** le composant enfant concerné (ex. `ValuationEvolutionChart` contient son propre `<Deferred>` + fallback skeleton), pour que chaque section gère son propre état de chargement.
- `RangeGranularityToggle` remonte les sélections via emits ; la page appelle `selectRange` / `selectGranularity` du composable.

## Gestion d'erreur / état vide

- Chaque composant conserve son état vide actuel (`<p>Pas encore…</p>`) et son skeleton de fallback `<Deferred>`. Comportement inchangé, seulement déplacé.

## Tests / vérification

- Pas d'infra de test JS dans le projet actuellement (aucun test Vue existant). On ne l'introduit pas ici.
- Vérification : `bun run build` (typecheck vue-tsc + build passent) puis contrôle visuel des deux pages dans le navigateur (Dashboard + une fiche instrument avec et sans position) — parité pixel avec l'existant.
- Aucun changement de comportement attendu : refactor pur.

## Hors périmètre (YAGNI)

- `Instruments/Index.vue` (97 l.) : non concerné.
- Aucun changement backend / props Inertia.
- Aucune nouvelle dépendance.
- Pas de refacto de `lib/chart.ts` (déjà extrait).
