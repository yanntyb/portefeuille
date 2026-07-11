# SP — Courbes d'investissement par titre

Date : 2026-07-11 · Branche : `vendredi-soir`

## Objectif

Deux visualisations réutilisant le contexte `Valuation` (série valeur/investi dans le temps) :

1. **Fiche instrument** (`/instruments/{id}`) : un graphe **Valeur vs Investi** pour CE titre, dans le même style que le graphe Évolution du dashboard.
2. **Dashboard** : un nouveau graphe **Investi cumulé par titre** — une ligne par titre sur un axe partagé, en plus du graphe Évolution agrégé existant.

## Périmètre

- **Dans le scope** : les deux graphes ci-dessus, livrés en props Inertia `deferred` (comme la série de valorisation actuelle).
- **Hors scope** : sélecteur de période ; variation du jour ; graphe valeur-par-titre (on ne fait que l'investi par titre côté dashboard) ; actifs perso.

## Décisions actées

- **Fiche** : garde le graphe de Cours **et** ajoute le graphe Valeur/Investi (ne remplace pas).
- **Dashboard** : inclut **tous les titres ayant des transactions** (même soldés, dont l'investi retombe vers ~0), pas seulement les titres détenus.
- **Type de graphe multi-titres** : **lignes** (une par titre, légende = noms). Pas d'aire empilée.
- Réutilise `ValuationCalculator` (pur) et `ValuationSeriesData` existants.

## Partie 1 — Fiche : valeur vs investi du titre

### Composition

- Nouvelle action `Valuation\Actions\BuildAssetValuationSeries(int $userId, int $assetId): ValuationSeriesData`.
  - Récupère les transactions de l'user (`TransactionHistoryPort::forUser`), **filtre sur `assetId`**.
  - Si aucune transaction pour ce titre → `ValuationSeriesData::empty()`.
  - `since` = date de la 1ʳᵉ transaction du titre ; prix via `PriceHistoryPort::forAssetsSince([$assetId], $since)`.
  - Délègue à `ValuationCalculator::calculate($assetTransactions, $prices)` — le calcul gère déjà quantité cumulée, PRU par asset et valorisation quotidienne ; avec un seul asset il produit la série de ce titre.
- Renvoie le `ValuationSeriesData` existant (`labels`, `valuations`, `invested`).

### Livraison

- `InstrumentView\Http\InstrumentDetailController` ajoute une prop **deferred** `valuation` :
  `Inertia::defer(fn () => app(BuildAssetValuationSeries::class)($userId, $id))`.
  - Le contrôleur (contexte InstrumentView) appelle une action Valuation via le container — même schéma que `Portfolio\Http\DashboardController` appelant `BuildPortfolioValuationSeries`.
- `resources/js/Pages/Instruments/Show.vue` : **2ᵉ graphe** area « Valeur vs Investi » sous le graphe Cours, options identiques à `Dashboard.vue` (`valuationChartOptions` : 2 séries `Valeur`/`Investi`, couleurs `#4f46e5`/`#64748b`, gradient). `<Deferred data="valuation">` + skeleton pulsant `#fallback` + état vide « Pas encore d'historique de valorisation. ».

## Partie 2 — Dashboard : investi cumulé par titre

### Nouveau port de nommage

- `Valuation\Ports\InstrumentDirectoryPort` :
  - `namesFor(array $assetIds): array<int, string>` — map `assetId => nom`.
- Adapter `Valuation\Infrastructure\MarketInstrumentDirectory implements InstrumentDirectoryPort` — lit `Market\Models\Instrument` (`whereIn('id', $ids)->pluck('name','id')`). Seul endroit lisant Market pour les noms.
- Bindé via `ValuationProvider::registers` (nouveau paramètre `instrumentDirectory`), câblé dans `AppServiceProvider`.

### Calcul pur

- Nouvelle méthode `ValuationCalculator::investedByAsset(array $transactions, int $maxPoints = 200): InvestedByAssetSeriesData`.
  - **Sans prix** : l'investi est une fonction en escalier sur les dates de transaction.
  - Pour chaque asset indépendamment (transactions triées date croissante, achat avant vente le même jour) : maintenir `buyQty`, `buyCost`, `invested`.
    - Achat : `invested += quantity*unitPrice + fees` ; `buyQty += quantity` ; `buyCost += quantity*unitPrice`.
    - Vente : `pru = buyQty>0 ? buyCost/buyQty : 0` ; `invested -= quantity*pru - fees`.
    - Enregistre `invested` cumulé à chaque date de transaction du titre.
  - `labels` = union triée de toutes les dates de transaction, plafonnée via `downsampleIndices`.
  - Pour chaque asset : tableau `invested` aligné sur `labels`, forward-fill (0 avant la 1ʳᵉ transaction du titre), `round(_, 2)`.
  - Formule d'investi **identique** à celle de `calculate()` (achat/vente/PRU/fees) pour cohérence avec le graphe agrégé.

### DTOs

- `Valuation\Datas\AssetInvestedSeriesData` (`readonly`, `JsonSerializable`) : `int $assetId`, `string $name`, `list<float> $invested`. JSON : `assetId`, `name`, `invested`.
- `Valuation\Datas\InvestedByAssetSeriesData` (`readonly`, `JsonSerializable`) : `list<string> $labels`, `list<AssetInvestedSeriesData> $series`. `::empty()` → `[], []`. JSON : `labels`, `series`.

### Action

- `Valuation\Actions\BuildInvestedByAssetSeries(int $userId): InvestedByAssetSeriesData`.
  - `transactions = TransactionHistoryPort::forUser($userId)` ; si vide → `::empty()`.
  - `series = ValuationCalculator::investedByAsset($transactions)` (assetId + invested, **sans noms**).
  - `names = InstrumentDirectoryPort::namesFor($assetIds)` ; attache le nom à chaque série (fallback `'#'.$assetId` si nom absent).
  - Retourne `InvestedByAssetSeriesData`.

### Livraison

- `Portfolio\Http\DashboardController` ajoute une prop **deferred** `investedByAsset` :
  `Inertia::defer(fn () => app(BuildInvestedByAssetSeries::class)($user->id))` (fallback `::empty()` si pas d'user).
- `resources/js/Pages/Dashboard.vue` : **nouvelle carte** « Investi par titre » sous le graphe Évolution. `<Deferred data="investedByAsset">` + skeleton + état vide « Pas encore d'investissement. ». Graphe **lignes** ApexCharts (`type: 'line'`), une série par `series[i]` (`name` = nom du titre, `data` = `invested`), `xaxis.categories = labels` (datetime), légende en bas, formatter EUR. Pas de couleurs imposées (palette ApexCharts par défaut).

## Cas limites

- Aucune transaction (user ou titre) → série vide → état vide dans l'UI.
- Titre sans prix (ex. crypto non cotée) : côté fiche, `valuations` vide (les jours viennent des prix) → graphe valeur vide, message ; l'investi par titre (dashboard) reste correct (ne dépend pas des prix).
- Titre soldé (quantité 0) : reste présent dans l'investi par titre, sa ligne redescend vers ~0.
- Nom d'instrument manquant : fallback `#<assetId>`.

## Tests (TDD, Pest)

**Unit :**
- `ValuationCalculator::investedByAsset` : 2 titres, dates entrelacées → axe partagé correct, forward-fill, cumul par titre ; vente réduit l'investi via PRU ; downsampling au-delà de `maxPoints`.
- `BuildAssetValuationSeries` : filtre bien sur le titre (transactions d'un autre titre ignorées) ; série non vide pour un titre détenu ; `::empty()` si aucune transaction du titre.
- `BuildInvestedByAssetSeries` : attache les noms (via port réel) ; plusieurs titres ; fallback nom manquant.
- `MarketInstrumentDirectory::namesFor` : renvoie la map id→nom, respecte le global scope market.
- DTOs `AssetInvestedSeriesData` / `InvestedByAssetSeriesData` : `jsonSerialize`.

**Feature :**
- Fiche `/instruments/{id}` : prop `valuation` **deferred** (absente au 1er render, chargée via `loadDeferredProps`, `labels`/`valuations`/`invested` présents pour un titre détenu avec prix).
- Dashboard `/dashboard` : prop `investedByAsset` **deferred** ; après chargement, `series` contient une entrée par titre transacté avec `name` + `invested`.

## Dette différée (notée)

- Formule d'investi dupliquée entre `calculate()` et `investedByAsset()` → extraire un accumulateur pur commun si un 3ᵉ usage apparaît.
- Graphe valeur-par-titre côté dashboard (non demandé) = itération future.
- Couleurs des séries non alignées avec les couleurs de type d'actif du dashboard.
