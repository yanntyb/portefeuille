# Câblage du dashboard sur les positions courantes

Date : 2026-07-11
Branche : `vendredi-soir`

## Objectif

Remplacer les données de démonstration de la page `Dashboard.vue` par les vraies
positions du portefeuille (instruments détenus), calculées côté serveur et
transmises via des props Inertia typées.

## Périmètre

**Dans le périmètre (v1)**

- Total de la valeur du portefeuille
- Gains / pertes latents (valeur − coût)
- Rendement (%)
- Donut de répartition par type d'instrument
- Table des positions détaillées

**Hors périmètre (phase 2)**

- Courbe valeur-dans-le-temps (nécessite historique / snapshots)
- Authentification réelle et scoping multi-utilisateur
- Writer transactions → `holdings_projection` (jalon SP3)
- Prise en compte des `PersonalAsset` (immobilier / épargne) dans le total

## Modèle de données existant

- `holdings_projection` : `asset_id`, `wallet_id`, `user_id`, `quantity`,
  `avg_cost`. Clé primaire composite (`asset_id`, `wallet_id`), pas de colonne `id`.
- `assets` : porte à la fois les `Instrument` (stock/etf/crypto/bond) et les
  `PersonalAsset` (real_estate/savings), séparés par global scope sur `type`.
- `asset_prices` : OHLC par date. Le prix retenu est `close`.
- `PriceRepositoryContract::latestForAsset(int $id): ?Price` fournit le dernier prix.

## Architecture et composants

Tout dans le contexte `Portfolio`, avec dépendance vers `Market` pour les prix.

1. **`App\Contexts\Portfolio\Models\Holding`** — read model Eloquent sur
   `holdings_projection`. `$incrementing = false`, `$table = 'holdings_projection'`.
   Relation `asset(): BelongsTo` → `Instrument` (clé `asset_id`). Casts
   `quantity`/`avg_cost` en `decimal:4`. `HoldingFactory` associée pour seed/tests.

2. **DTOs** (`App\Contexts\Portfolio\Datas\`, style maison : `readonly` +
   `fromArray`), l'agrégat implémente `JsonSerializable` pour la sérialisation Inertia :
   - `PortfolioOverviewData` — `totalValue`, `totalCost`, `totalGain`,
     `totalGainPct`, `HoldingLineData[]`, `AllocationSliceData[]`.
   - `HoldingLineData` — `assetName`, `ticker`, `type`, `quantity`, `avgCost`,
     `lastPrice`, `marketValue`, `gain`, `gainPct`.
   - `AllocationSliceData` — `label`, `value`, `pct`, `color` (hex).

3. **`App\Contexts\Portfolio\Actions\GetPortfolioOverview`** — action invokable,
   `__invoke(User $user): PortfolioOverviewData`. Injecte `PriceRepositoryContract`.
   Charge les holdings du user (eager `asset`), récupère le dernier `close` par
   asset, calcule valeur / coût / gain par ligne, agrège les totaux et la
   répartition par `InstrumentType`.

4. **`App\Contexts\Portfolio\Http\DashboardController`** — controller invokable,
   fin. Résout l'utilisateur courant, délègue à `GetPortfolioOverview`, renvoie
   `Inertia::render('Dashboard', ['overview' => $overview])`. Aucune logique métier.

5. **`resources/js/Pages/Dashboard.vue`** — reçoit la prop `overview` typée (TS),
   remplace les constantes de démo. Ajoute le composant shadcn **Table** pour les
   positions. Donut, KPI et table branchés sur `overview`.

## Data flow

```
GET /dashboard
  → DashboardController::__invoke
    → GetPortfolioOverview(user)
      → Holding::with('asset')->where('user_id', ...) + PriceRepository::latestForAsset
      → PortfolioOverviewData
  → Inertia::render('Dashboard', ['overview' => data])
  → Dashboard.vue (props.overview)
```

## Calculs

Par position :
- `marketValue = quantity × lastClose`
- `cost = quantity × avgCost`
- `gain = marketValue − cost`
- `gainPct = cost > 0 ? gain / cost × 100 : 0`

Agrégats :
- `totalValue = Σ marketValue`
- `totalCost = Σ cost`
- `totalGain = totalValue − totalCost`
- `totalGainPct = totalCost > 0 ? totalGain / totalCost × 100 : 0`

Répartition : regroupement des `marketValue` par `InstrumentType`, `pct` = part du
`totalValue`. Couleur = mapping `InstrumentType` → hex (défini dans le DTO/action,
les anciennes couleurs Filament ne s'appliquent plus).

## Décisions

- **Base du donut** : par `InstrumentType`. La répartition par secteur viendra plus tard.
- **Scope utilisateur** : `auth()->user() ?? User::first()` — pas d'auth encore,
  hypothèse mono-utilisateur.
- **Prix manquant** : un asset sans prix connu → position exclue des totaux et du
  donut, mais affichée dans la table avec la mention « prix indisponible ». Jamais d'erreur.
- **Multi-wallet** : agrégation de tous les wallets de l'utilisateur en v1.
- **Placement controller** : dans le contexte (`Portfolio/Http/`) pour la cohérence DDD.

## Développement en TDD

Ordre red → green → refactor :

1. Unit `GetPortfolioOverviewTest` : valeur = qty×close ; gain = value−(qty×avgCost) ;
   agrégats totaux ; répartition par type ; cas prix manquant.
2. Implémenter `Holding` + `HoldingFactory`, les DTOs, puis `GetPortfolioOverview`
   jusqu'au vert.
3. Feature `DashboardPageTest` (mise à jour) : `/dashboard` → 200 +
   `assertInertia` vérifiant la forme de `overview` (seed holdings + prix via factories).
4. Frontend : brancher `Dashboard.vue` sur `overview`, ajouter la table shadcn.

## Tests

- Unit : `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php`
- Feature : `tests/Feature/DashboardPageTest.php` (étendu)
- Factories : `HoldingFactory`, réutilisation de `InstrumentFactory` et `PriceFactory`
