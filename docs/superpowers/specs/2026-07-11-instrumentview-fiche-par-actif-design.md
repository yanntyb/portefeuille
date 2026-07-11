# SP — Fiche par instrument (contexte `InstrumentView`)

Date : 2026-07-11 · Branche : `vendredi-soir`

## Objectif

Offrir une **page par actif** : une fiche complète détaillant un instrument (position, courbe de prix, transactions, secteurs) plus un **catalogue** listant tous les instruments. Réutilise le seam DDD établi (ports + adapters, comme le contexte `Valuation`).

## Périmètre

- **Dans le scope** : instruments Market (`stock`/`etf`/`crypto`/`bond`), détenus ou non. La section « ma position » ne s'affiche que si l'utilisateur détient l'actif ET qu'un prix est disponible.
- **Hors scope v1** : actifs perso (`realestate`/`savings`, `Portfolio\PersonalAsset`) ; recherche/filtre plein-texte ; édition de transactions ; données de marché temps réel (variation du jour).

## Architecture — nouveau contexte `app/Contexts/InstrumentView/`

Contexte **read-only** qui compose ses vues via ports depuis Market et Portfolio. Il ne lit jamais un model d'un autre contexte en direct : uniquement à travers des adapters anti-corruption, bindés par un `InstrumentViewProvider` enregistré dans `AppServiceProvider` (même mécanique que `ValuationProvider`).

Règle d'or respectée : cross-contexte par **id + Port/Query**, jamais de relation Eloquent entre contextes.

### Ports (`InstrumentView\Ports`)

- `MarketDataPort`
  - `listInstruments(): Collection<InstrumentSummary>` — id, name, ticker, type
  - `findInstrument(int $id): ?InstrumentMeta` — id, name, ticker, isin, type
  - `priceHistory(int $id, Carbon $since): Collection<PricePoint>` — date, close
  - `sectors(int $id): Collection<SectorWeight>` — label, weight
- `HoldingsPort`
  - `holdingsFor(int $userId): Collection<HoldingSnapshot>` — assetId, quantity, avgCost
  - `holdingFor(int $userId, int $assetId): ?HoldingSnapshot`
- `TransactionsPort`
  - `transactionsFor(int $userId, int $assetId): Collection<TransactionSnapshot>` — date, type, quantity, unitPrice, fees

Les types de retour des ports sont des DTO/valeurs internes au contexte (`InstrumentView\Ports\Data` ou réutilisation des `Datas`), afin de ne pas fuiter les models Market/Portfolio dans le contexte.

### Adapters (`InstrumentView\Infrastructure`)

- `MarketData implements MarketDataPort` — lit `Market\Models\Instrument` (+ global scope market), `Market\Models\SectorAllocation`, et délègue les prix à `Market\Contracts\PriceRepositoryContract` (`forAssetSince`, `latestForAsset`).
- `PortfolioHoldings implements HoldingsPort` — lit `Portfolio\Models\Holding` (`holdings_projection`, read-only).
- `PortfolioTransactions implements TransactionsPort` — lit `Portfolio\Models\Transaction` filtré par `user_id` + `asset_id`.

Binding via `InstrumentViewProvider::registers` appelé dans `AppServiceProvider`.

## Pages

### Catalogue — `GET /instruments` → `Pages/Instruments/Index.vue`

Table de tous les instruments : nom, ticker, type (badge), dernier prix, indicateur « détenu » (avec quantité + valeur si détenu). Ligne cliquable vers la fiche. Tri par colonne côté client. Pas de recherche (le catalogue liste déjà tout — YAGNI).

Vide → message « Aucun instrument connu. ».

### Fiche — `GET /instruments/{id}` → `Pages/Instruments/Show.vue`

Param de route `{id}` entier (pas de model-binding cross-contexte). Le controller résout via `MarketDataPort::findInstrument` ; `null` → **404**.

Sections :

1. **Header** — nom, ticker, ISIN, type (label), dernier prix + sa date.
2. **Ma position** — masquée si non détenu ou si prix indisponible. Qté, PRU (avg_cost), valeur de marché, gain €, gain %. Livrée en prop `Inertia::defer` + skeleton pulsant.
3. **Courbe de prix** — historique `close` sur **12 mois** (`priceHistory(id, now->subMonths(12))`), ApexCharts area. Prop `Inertia::defer` + skeleton. Message si aucun historique.
4. **Transactions** — mes lignes pour cet actif (date, sens achat/vente, quantité, prix unitaire, frais, total = qté×prix±frais), triées date décroissante. Message si aucune.
5. **Secteurs** — `SectorAllocation` (label + poids) si disponible, sinon section masquée.

## Composition applicative

- Actions : `InstrumentView\Actions\GetInstrumentCatalog(int $userId)` et `GetInstrumentDetail(int $userId, int $instrumentId)`.
- DTOs `JsonSerializable` (`InstrumentView\Datas`) : `InstrumentCatalogLineData`, `InstrumentDetailData`, `PositionData`, `PriceHistoryData` (`labels: string[]`, `close: number[]`), `TransactionLineData`, `SectorWeightData`.
- Controllers invokables `InstrumentView\Http\{InstrumentCatalogController, InstrumentDetailController}`.
- Calcul du gain (valeur − coût, gain %) : même formule que `Portfolio\Actions\GetPortfolioOverview` (`gain = marketValue − quantity×avgCost` ; `gainPct = gain / cost × 100` si `cost > 0`). Calcul inline dans l'action détail ; extraction d'un helper pur partagé notée comme dette différée (non bloquante).
- L'utilisateur courant suit la convention Dashboard : `auth()->user() ?? User::query()->first()` (mono-user v1).

## Lien depuis le Dashboard

- Ajouter `assetId: int` à `Portfolio\Datas\HoldingLineData` (peuplé dans `GetPortfolioOverview` depuis `$holding->asset_id`) et à l'interface `HoldingLine` de `Dashboard.vue`.
- Les lignes de la table Positions deviennent des `<Link href="/instruments/{assetId}">` (Inertia), avec style de survol.

## Erreurs & cas limites

- `{id}` inconnu → 404 (`abort(404)` après `findInstrument` null).
- Instrument non détenu → section position masquée, reste de la fiche affiché.
- Prix indisponible → dernier prix « N/D », position masquée (cohérent avec l'exclusion des actifs non cotés dans `overview.totalCost`).
- Aucun historique de prix → message dans la section courbe.
- Aucune transaction → message dans la section transactions.

## Tests (TDD, Pest)

**Feature :**
- `GET /instruments` rend `Instruments/Index` avec la liste de tous les instruments + flag détenu correct.
- `GET /instruments/{id}` détenu : rend `Instruments/Show`, section position présente, transactions listées, courbe deferred résolue.
- `GET /instruments/{id}` non détenu : position absente, reste présent.
- `GET /instruments/{id}` inconnu → 404.
- Dashboard : chaque ligne Positions porte l'`assetId` attendu.

**Unit :**
- `MarketData` : `listInstruments`/`findInstrument`/`priceHistory`/`sectors` renvoient les bons DTO ; respecte le global scope market.
- `PortfolioHoldings` : `holdingFor`/`holdingsFor` filtrent par user.
- `PortfolioTransactions` : `transactionsFor` filtre user+asset, tri desc.
- Assemblage : `GetInstrumentDetail` compose position + gain correct ; masque position si non détenu.

## Décisions

- **Nom du contexte** : `InstrumentView`.
- **Fenêtre historique prix** : 12 mois (cohérent avec la courbe Dashboard SP4).
- **Identifiant de route** : id entier, résolution par port (pas de route-model-binding cross-contexte).
- **Actifs perso** : hors scope v1.

## Dette différée (notée, non bloquante)

- Calcul du gain dupliqué avec `GetPortfolioOverview` → extraire un helper pur quand un 3ᵉ consommateur apparaît.
- Variation du jour (prix J vs J-1) non affichée (nécessite 2 prix consécutifs fiables).
- Fenêtre prix figée à 12 mois (sélecteur de période = évolution future).
- Fiche pour actifs perso (immo/épargne) = itération ultérieure.
