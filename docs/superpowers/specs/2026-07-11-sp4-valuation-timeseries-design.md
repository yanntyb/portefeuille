# SP4 — Valuation : courbe valeur dans le temps

Date : 2026-07-11
Branche : `vendredi-soir`

## Objectif

Créer le contexte `Valuation` qui reconstruit la **valeur du portefeuille dans le
temps** (valeur de marché + montant investi), et l'afficher sur le dashboard via
une courbe chargée en **prop Inertia deferred** (avec skeleton). C'est la « phase 2 »
laissée de côté au câblage du dashboard.

## Périmètre

**Dans le périmètre**
- Contexte `Valuation` : ports vers Portfolio (transactions) et Market (prix),
  calculateur pur, action orchestratrice
- Série `valeur + investi` dans le temps (DTO sérialisable)
- Dashboard : prop deferred `valuationSeries` + courbe ApexCharts (area, 2 séries)
  avec skeleton pulsant pendant le chargement
- Historique de prix synthétique dans le seeder démo (sinon courbe plate)

**Hors périmètre**
- Frais dans la série (pas de wallet_fees encore)
- Granularité configurable, plages custom, cache
- SP5 Simulation

## Architecture — contexte `Valuation` (`app/Contexts/Valuation/`)

Règle d'or respectée : cross-contexte par **Port + adapter** (anti-corruption),
jamais de relation Eloquent entre contextes.

**Ports (interfaces définies par Valuation)**
- `Valuation\Ports\TransactionHistoryPort` — `forUser(int $userId): list<TransactionRecordData>`
  (triées par date croissante).
- `Valuation\Ports\PriceHistoryPort` — `forAssetsSince(array $assetIds, Carbon $since): list<PriceRecordData>`.

**Adapters (le seam qui connaît les autres contextes)**
- `Valuation\Infrastructure\PortfolioTransactionHistory implements TransactionHistoryPort`
  — lit `Portfolio\Models\Transaction`, mappe en `TransactionRecordData`.
- `Valuation\Infrastructure\MarketPriceHistory implements PriceHistoryPort`
  — enveloppe `Market\Contracts\PriceRepositoryContract::forAssets(...)`, mappe en `PriceRecordData`.
- Bindings via `Valuation\ValuationProvider::registers(...)` appelé depuis `AppServiceProvider`
  (même patron que `MarketProvider`).

**DTOs (`Valuation\Datas`, readonly)**
- `TransactionRecordData(Carbon $date, int $assetId, bool $isSell, float $quantity, float $unitPrice, float $fees)`
  — `bool $isSell` plutôt que l'enum de Portfolio, pour ne rien fuiter.
- `PriceRecordData(int $assetId, string $date, float $close)` (date `Y-m-d`).
- `ValuationSeriesData(list<string> $labels, list<float> $valuations, list<float> $invested)`
  — implémente `JsonSerializable` (clés `labels`, `valuations`, `invested`) ; `empty(): self`.

**Cœur**
- `Valuation\Services\ValuationCalculator` — **pur, sans DB** :
  `calculate(list<TransactionRecordData> $transactions, list<PriceRecordData> $prices): ValuationSeriesData`.
  1. Cumule par asset la quantité dans le temps, et le montant investi cumulé
     (achat : `+qty×prix+frais` ; vente : `−(qty×PRU−frais)`, PRU = coût moyen des
     achats à ce moment). Port fidèle de l'ancien `TransactionAggregator`.
  2. Jours = dates de prix distinctes triées. Pour chaque jour : forward-fill du
     dernier close par asset, `valeur = Σ qty_au_jour × close` ; `investi = investi_au_jour`.
  3. Retourne labels/valuations/invested (arrondis 2 décimales).
- `Valuation\Actions\BuildPortfolioValuationSeries` — invokable
  `__invoke(int $userId): ValuationSeriesData`. Récupère les transactions (port),
  déduit `since` (1ère date) et les asset ids, récupère les prix depuis `since` (port),
  délègue au calculateur. Série vide si aucune transaction.

## Livraison dashboard

- `DashboardController` ajoute une prop **deferred** :
  `'valuationSeries' => Inertia::defer(fn () => app(BuildPortfolioValuationSeries::class)($userId))`
  (série vide si aucun user).
- `Dashboard.vue` réintègre une carte **courbe** (ApexCharts `area`, 2 séries
  « Valeur » + « Investi », xaxis = labels), enveloppée dans `<Deferred data="valuationSeries">`
  avec un `#fallback` = skeleton pulsant (`animate-pulse`). Flat design conservé.

## Données démo

- `DashboardDemoSeeder` : au lieu d'un seul prix (aujourd'hui) par instrument coté,
  générer un historique **hebdomadaire sur ~12 mois** finissant au `close` actuel
  (interpolation `buyPrice → close` avec légère ondulation). La courbe devient visible.

## Flux

```
GET /dashboard (render initial, sans valuationSeries)
  → requête deferred suivante → Inertia::defer callback
    → BuildPortfolioValuationSeries(userId)
      → TransactionHistoryPort.forUser + PriceHistoryPort.forAssetsSince
      → ValuationCalculator.calculate → ValuationSeriesData
  → prop valuationSeries → <Deferred> remplace le skeleton par la courbe
```

## Décisions

- **Série** : valeur + investi (2 courbes). Frais exclus (pas de wallet_fees).
- **Cross-contexte** : ports Valuation + adapters (anti-corruption), réutilise le
  `PriceRepositoryContract` de Market derrière `MarketPriceHistory`.
- **Timeline** : jours = dates de prix distinctes ≥ 1ère transaction (forward-fill).
- **Livraison** : prop deferred + skeleton (perçu plus rapide, calcul lourd hors render initial).
- **Type de transaction** exposé en `bool $isSell` dans le DTO (pas d'enum Portfolio fuité).

## Développement en TDD

1. DTOs `Valuation\Datas\*` (+ test sérialisation `ValuationSeriesData`).
2. `ValuationCalculator` pur (+ test : buy simple sur 2 dates → valeurs/investi ; ajout d'une vente ; asset sans prix).
3. Ports + adapters `PortfolioTransactionHistory` / `MarketPriceHistory` + `ValuationProvider` binding (+ tests adapters DB : mapping correct).
4. Action `BuildPortfolioValuationSeries` (+ test via container : série cohérente, vide si pas de transaction).
5. Dashboard : prop deferred + `Dashboard.vue` courbe + skeleton (+ feature test `->missing('valuationSeries')` puis `->loadDeferredProps(...->has('valuationSeries.valuations'))` ; typecheck + build).
6. Seeder : historique de prix synthétique (+ maj test seeder : dates de prix distinctes > 1, série valuations non plate).

## Tests

- Unit : `ValuationCalculatorTest` (`app/Contexts/Valuation/Services/`), `ValuationSeriesDataTest`.
- Adapters : `PortfolioTransactionHistoryTest`, `MarketPriceHistoryTest` (`app/Contexts/Valuation/Infrastructure/`).
- Action : `BuildPortfolioValuationSeriesTest` (`app/Contexts/Valuation/Actions/`).
- Feature : `DashboardPageTest` étendu (deferred), `DashboardDemoSeederTest` étendu (historique).
