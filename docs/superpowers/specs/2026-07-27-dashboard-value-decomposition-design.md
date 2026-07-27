# Décomposition de la valeur par titre + visibilité pilotée par la table

Date : 2026-07-27
Statut : design validé (brainstorming). Fait évoluer le graph fusionné
(`2026-07-27-dashboard-evolution-merge-design.md`).

## Context

Le graph « Évolution » actuel empile l'**investi par titre** (aires grises), avec une ligne
Valeur et une bande gain/perte, et une légende cliquable désactivée. On change de modèle :

1. Les aires empilées doivent représenter la **valeur de marché de chaque titre** dans le
   temps (quantité(t) × dernier prix connu), pas l'investi.
2. La **visibilité** de chaque titre est pilotée par une **colonne œil** dans la table
   Positions — plus par la légende du graph, qui **disparaît**.
3. La **valeur totale** est le sommet du stack = somme des titres **visibles** (calcul front).
4. Plus de bande gain/perte ni de ligne Valeur séparée : gain/perte lu dans le **tooltip**.

## Décisions (validées)

- Aires empilées grises = **valeur de marché par titre** ; sommet = valeur totale.
- Aucune légende, aucune bande `rangeArea`, aucune ligne Valeur distincte sur le graph.
- Colonne **œil** (ouvert/barré) dans la table Positions, une par ligne ; défaut : tous
  visibles ; masquer retire l'aire → le total baisse d'autant. Nom grisé si masqué.
- Couplage table ↔ graph par **`assetId`** (état front `hiddenAssetIds: Set<number>`).
- Tooltip : date + **Valeur** (Σ valeur visibles) + **Gain/perte** (Σ valeur − Σ investi
  visibles, vert/rouge) + valeur par titre visible.
- Palette : aires en **gris** (dégradé slate), inchangé.

Hors périmètre : pas de « valeur par titre » colorée ; un titre entièrement vendu (présent
dans l'historique mais absent de Positions) reste visible par défaut (pas de ligne pour le
masquer).

## Architecture

### Backend — `app/Contexts/Valuation`

`ValuationCalculator::evolution()` doit exposer, par actif, **la valeur de marché** en plus
de l'investi, alignée sur les labels de la valorisation windowée.

- Valeur par actif à un label = `quantité(label) × dernier close connu(label)` (forward-fill),
  exactement la logique que `calculateDaily()` applique déjà pour le total (lignes 102-118).
- Réutilise `valueAtDate` : construire par actif une série quantités `[{date, value:qty}]`
  (cumul sur les transactions triées) et une série prix `[{date, value:close}]` (triée). Alors
  `value[i] = round(valueAtDate(qty, label) × valueAtDate(price, label), 2)` — `valueAtDate`
  renvoie 0 si aucune entrée ≤ label (titre pas encore acheté / pas encore coté → 0), ce qui
  reproduit le `continue` de `calculateDaily`.
- Nouveau helper privé symétrique à `perAssetInvestedTimelines`, p. ex.
  `perAssetQuantityTimelines(transactions)` + regroupement des prix par actif.

`EvolutionSeriesData` change de forme :
```
{
  labels: list<string>,                 // 'Y-m-d'
  perAsset: list<{ assetId, name, value: list<float>, invested: list<float> }>
}
```
On **supprime** les top-level `value[]` / `totalInvested[]` (le front somme selon la
visibilité). `AssetInvestedSeriesData` est étendu (ou remplacé par un DTO
`AssetSeriesData { assetId, name, value[], invested[] }`) — voir plan. Invariant testable :
`Σ perAsset.value[i]` == valorisation totale windowée à `labels[i]`.

`BuildEvolutionSeries` : inchangé sur le principe (résout les noms via le directory), passe
`value[]` + `invested[]`.

### Frontend

`resources/js/lib/chart.ts` — `buildEvolutionChart` **réécrit et simplifié** :
- Entrée : `{ labels, perAsset: [{assetId, name, value[], invested[]}], hiddenIds: Set<number>, valueFormatter }`.
- Ne garde que les actifs **visibles** (`!hiddenIds.has(assetId)`), dans l'ordre stable.
- N aires empilées (pré-cumul manuel des **valeurs**), grises (`GREY_SCALE`).
- **Supprime** : séries `rangeArea` gain/loss, ligne `Valeur`, la légende (`legend.show:false`),
  le hack `hideBandLegendItems`, `onItemClick`, `formatter`, `BAND_NAMES`, `VALUE_LINE_COLOR`.
- Tooltip custom `shared` : date + Valeur (Σ valeur visibles au point) + Gain (Σ valeur −
  Σ investi visibles, vert `#10b981` / rouge `#ef4444`) + une ligne valeur par titre visible.
  Réutilise `formatTooltipDate`. `buildTimeSeriesOptions`/`buildDonutOptions` inchangés.

`resources/js/Pages/Dashboard.vue` :
- Interface `EvolutionSeries` → `{ labels, perAsset: {assetId, name, value[], invested[]}[] }`.
- État `const hiddenAssetIds = ref<Set<number>>(new Set())` + `toggleAsset(id)`.
- `evolutionChart` computed passe `hiddenIds: hiddenAssetIds.value` à `buildEvolutionChart`.
- **Table Positions** : nouvelle colonne (en-tête vide ou icône) ; par ligne un bouton œil
  (`Eye` / `EyeOff` de lucide, déjà utilisé ailleurs si dispo — sinon SVG inline) qui appelle
  `toggleAsset(line.assetId)` ; `line.assetName` grisé (`text-muted-foreground`) si masqué.

### Tests

- **Backend (Pest)** : `evolution` renvoie `perAsset.value[]` correct (forward-fill prix,
  valeur 0 avant 1er achat, mise à jour à chaque nouveau close), invariant
  `Σ perAsset.value[i]` == valorisation totale. Maj `EvolutionSeriesData` (+ son test) et
  `BuildEvolutionSeries` test (nouvelle forme). Maj `DashboardPageTest` (forme du prop).
- **Frontend** : `bun run build` ; Playwright `/dashboard` desktop+mobile : aires grises =
  valeur, plus de légende, clic œil dans Positions masque l'aire et baisse le sommet + le
  total du tooltip, tooltip = Valeur + Gain + valeur/titre. Maj `DashboardChartLegendTest`
  (retirer `assertSee('Valeur')` ; `.apx-legend-position-left` 2 → 1 ; ajouter une assertion
  sur la colonne œil / le toggle).

## Migration depuis le graph fusionné existant

Ce design **remplace** la partie « aires = investi + bande + ligne + légende » du graph
fusionné livré précédemment. Le pré-cumul manuel et le tooltip custom sont conservés dans
leur principe ; les bandes/ligne/légende sont retirées ; la source des aires passe de
`invested` à `value`.
