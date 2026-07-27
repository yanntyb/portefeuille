# Fusion des graphs Évolution + Investi par titre

Date : 2026-07-27
Statut : design validé (brainstorming)

## Context

Le Dashboard affiche deux graphiques temporels distincts :
- **Évolution** (aire) : deux séries totales, `Valeur` (valorisation du portefeuille) et `Investi`.
- **Investi par titre** (ligne stepline) : une ligne par actif du montant investi cumulé.

La ligne `Investi` du premier graph est exactement la somme des lignes du second. On veut
**fusionner les deux en un seul graphique** qui décompose l'investi par actif (aires empilées)
et superpose la valorisation totale, avec une lecture immédiate du gain/perte.

## Décisions (validées)

1. **Un seul graphique** remplace les deux cards.
2. **Décomposition par actif (titre)** : une couche par actif, empilée ; le sommet du stack = total investi.
3. **Ligne `Valeur`** (valorisation totale) tracée par-dessus.
4. **Bande gain/perte** : l'écart entre le sommet de l'investi et la ligne `Valeur` est rempli
   **vert** si `valeur ≥ investi`, **rouge** si `valeur < investi`.
5. **Palette** : aires des actifs en **dégradé de gris/neutre** ; couleur réservée à la bande
   gain/perte (vert/rouge) et à la ligne `Valeur` (indigo).
6. **Card** : titre `Évolution`, description « Valeur, investi par titre et performance ».
   Le sélecteur période/granularité reste dans le `CardHeader`. La card « Investi par titre »
   est **supprimée**. Donut Répartition + table Positions inchangés.

## Architecture

### Données (backend) — `app/Contexts/Valuation`

Problème : `valuationSeries` (labels bucketisés par range/granularité) et `investedByAsset`
(labels = union des dates de transactions) n'ont pas le même axe X. La superposition impose
**un seul jeu de labels**.

Nouvelle méthode dans `ValuationCalculator` (ex. `evolution(...)`) produisant une série alignée :

1. `calculateDaily()` puis `windowAndAggregate(range, granularity)` → labels finaux + `value[]`
   + `totalInvested[]` (réutilise l'existant, aucune duplication de logique).
2. Construction des timelines d'investi **par actif** (même logique que `investedByAsset()` :
   accumulation `$perAsset[$assetId][] = ['date', 'value']`).
3. Pour chaque label final : `invested_actif = valueAtDate(entriesActif, label)` (helper existant).
   Invariant : `sum(perAsset.invested) == totalInvested` (à vérifier en test).
4. Résolution `assetId → name` (comme `investedByAsset()`).

Réutilise : `valueAtDate`, `windowAndAggregate`, `downsampleIndices`, `calculateDaily`.

Nouveau DTO `EvolutionSeriesData` (`app/Contexts/Valuation/Datas/`) — `toArray()` :

```
{
  labels: list<string>,          // 'Y-m-d'
  value: list<float>,            // valorisation totale
  totalInvested: list<float>,    // somme des couches
  perAsset: list<{ name: string, invested: list<float> }>  // ordre stable
}
```

Controller `DashboardController` : remplace les props `valuationSeries` + `investedByAsset`
par un seul prop **déféré** `evolutionSeries` (même mécanisme `Inertia::defer` qu'aujourd'hui,
mêmes paramètres `range`/`granularity`). Le `router.reload` frontend passe à `only: ['evolutionSeries']`.

### Graphique (frontend) — ApexCharts mixte

ApexCharts n'empile pas des aires tout en gardant une ligne absolue indépendante
(`chart.stacked` empile **toutes** les séries). On n'utilise donc **pas** `chart.stacked` et on
**pré-cumule manuellement** :

- **N séries `area`** (une par actif) : la couche `i` porte le **cumul** `somme(invested[0..i])`.
  Tracées de la plus grande (au fond) à la plus petite (au premier plan) → rendu empilé.
  Remplissage gris semi-transparent, couleur par index dans une échelle neutre
  (ex. slate `#e2e8f0 → #334155`, cyclée si > 6 actifs).
- **Bande gain/perte** = 2 séries `rangeArea` :
  - verte `{ low: totalInvested[i], high: value[i] }` là où `value ≥ investi`, sinon `null` ;
  - rouge `{ low: value[i], high: totalInvested[i] }` là où `value < investi`, sinon `null`.
  - Vert `#10b981`, rouge `#ef4444`, fill semi-transparent. Exclues de la légende.
- **Ligne `Valeur`** : série `line`, absolue (`value[]`), indigo `#4f46e5`, épaisseur 2.

Options : nouvelle fabrique `buildEvolutionOptions(...)` dans `resources/js/lib/chart.ts`
(réutilise `formatTooltipDate`). `type` défini par série (chart mixte, pas de `chart.stacked`).

- **Tooltip** : custom `shared`, construit : date + `Valeur` + `Gain/Perte`
  (`value − totalInvested`, coloré) + une ligne d'investi **par actif** (dé-cumul via les
  tableaux bruts `perAsset` capturés en closure, indexés par `dataPointIndex`). Le tooltip
  « overlap » actuel de `buildTimeSeriesOptions` reste inchangé pour les autres graphs
  (page Instrument).
- **Légende** : actifs (gris) + `Valeur`. Bandes gain/perte masquées
  (`legend.formatter` blanchit leurs libellés + marqueur 0px, ou équivalent). Toggle désactivé
  (`legend.onItemClick.toggleDataSeries: false`) car masquer une couche casserait le pré-cumul.
- Réutilise `LEGEND_BELOW_ON_MOBILE` + `horizontalAlign: 'left'`.

### Layout — `resources/js/Pages/Dashboard.vue`

- La card `Évolution` accueille le graph fusionné (sélecteur déjà dans le header).
- Suppression complète de la card « Investi par titre » et de ses computed
  (`investedByAssetSeries`, `investedByAssetOptions`, `hasInvestedByAsset`).
- Nouveaux computed dérivés de `evolutionSeries` : séries cumulées, bandes, ligne, options.

## Composants / responsabilités

- `ValuationCalculator::evolution()` : calcul aligné value + investi par actif. Testable seul.
- `EvolutionSeriesData` : sérialisation Inertia. Testable seul (`toArray`, invariant somme).
- `buildEvolutionOptions()` (`lib/chart.ts`) : options ApexCharts + tooltip. Pur, sans I/O.
- `Dashboard.vue` : assemble props → séries (pré-cumul, bandes) → composant chart.

## Tests / vérification

- **Backend (Pest)** : `ValuationCalculator` — labels alignés, `sum(perAsset)==totalInvested`,
  `value` correcte, cas perte (`value<investi`). DTO `EvolutionSeriesData::toArray` — forme +
  invariant. S'appuyer sur les patterns de `ValuationSeriesDataTest` / `InvestedByAssetSeriesDataTest`.
- **Frontend** : `bun run build`, puis Playwright sur `/dashboard` (desktop + mobile 420px) :
  aires grises empilées, ligne Valeur indigo, bande verte (portefeuille en gain), tooltip
  listant Valeur + Gain + investi par actif ; disparition de la card « Investi par titre ».
- Cas perte : vérifier bande rouge sur un jeu où `value<investi` (fixture de test dédiée).

## Hors périmètre (YAGNI)

- Pas de « valeur par actif » (donnée absente côté back ; seule la valeur totale existe).
- Pas de toggle interactif des couches.
- Pas de refonte du donut / de la table.
