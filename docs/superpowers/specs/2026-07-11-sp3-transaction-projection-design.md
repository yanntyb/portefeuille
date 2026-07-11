# SP3 — Transaction → projection holdings_projection

Date : 2026-07-11
Branche : `vendredi-soir`

## Objectif

Porter le domaine **Transaction** dans le contexte `Portfolio` (DDD) et alimenter
automatiquement la projection `holdings_projection` (positions courantes) à partir
des transactions, pour que le dashboard affiche des données dérivées du réel et non
d'un seed manuel. Calculer aussi le gain réalisé (`realized_gain`) sur les ventes.

C'est le writer manquant identifié après le câblage read-only du dashboard
(`docs/superpowers/specs/2026-07-11-dashboard-portfolio-overview-design.md`).

## Périmètre

**Dans le périmètre**
- Modèle `Transaction` + enum `TransactionType` (Portfolio, sans Filament)
- Calcul `realized_gain` sur les ventes
- Projection automatique de `holdings_projection` (quantité + PRU) sur
  création / modification / suppression de transaction
- Réécriture du `DashboardDemoSeeder` via des transactions (preuve end-to-end)

**Hors périmètre**
- `wallet_fees` (prélèvements sur gains), `allocation_profiles`
- Courbe valeur-dans-le-temps / valorisations quotidiennes (SP4)
- Authentification (le contexte reste no-auth, requêtes par `user_id` explicite)

## Modèle de données existant

Table `transactions` : `id`, `date`, `asset_id` (nullable, FK `assets` nullOnDelete),
`wallet_id` (FK `wallets`), `user_id` (FK `users`), `type` (string, défaut `buy`),
`quantity` (decimal 12,4 nullable), `unit_price` (decimal 12,4 nullable),
`fees` (decimal 10,2 défaut 0), `realized_gain` (decimal 12,2 nullable),
`broker` (nullable), `notes` (nullable), timestamps.

Table `holdings_projection` : PK composite `(asset_id, wallet_id)`, `user_id`,
`quantity`, `avg_cost` (voir modèle `Portfolio\Models\Holding` déjà en place).

## Architecture (contexte Portfolio)

Découpage : **Observer mince = glue** vers des **Actions = logique pure**.

1. **`Portfolio\Enums\TransactionType`** — `Buy = 'buy'`, `Sell = 'sell'`,
   `values(): list<string>`, `getLabel(): string` (« Achat » / « Vente »).
   Aucune dépendance Filament.

2. **`Portfolio\Models\Transaction`** — table `transactions`, `$guarded = ['id']`.
   Casts : `date` → `date`, `type` → `TransactionType`, `quantity`/`unit_price` →
   `decimal:4`, `fees`/`realized_gain` → `decimal:2`. Relations `wallet(): BelongsTo`,
   `user(): BelongsTo`. **`asset_id` référencé par id seul** (pas de relation Eloquent
   vers `Market\Instrument` — règle cross-contexte : id + Port/Query). Attribut
   `#[ObservedBy(TransactionObserver::class)]`. `TransactionFactory` avec états
   `buy()` / `sell()`.

3. **`Portfolio\Actions\CalculateRealizedGain`** — invokable
   `__invoke(Transaction $transaction): ?float`.
   - Retourne `null` si `type !== Sell`.
   - Sinon PRU = Σ(`quantity` × `unit_price`) ÷ Σ`quantity` sur les **achats du même
     `(user_id, wallet_id, asset_id)` de date ≤ celle de la vente** (version correcte ;
     l'ancien système prenait tous les achats sans filtre de date).
   - Retourne `round((unit_price − PRU) × quantity − fees, 2)`.

4. **`Portfolio\Actions\ProjectHolding`** — invokable
   `__invoke(int $userId, int $assetId, int $walletId): void`.
   - `buyQty` = Σ`quantity` des achats, `buyCost` = Σ(`quantity` × `unit_price`) des
     achats, `soldQty` = Σ`quantity` des ventes, pour `(user, asset, wallet)`.
   - `quantity = buyQty − soldQty`.
   - Si `quantity <= 0` → supprime la ligne `Holding` `(asset, wallet)` et retourne.
   - Sinon `avgCost = buyQty > 0 ? buyCost / buyQty : 0`, puis
     `Holding::updateOrCreate(['asset_id','wallet_id'], ['user_id','quantity','avg_cost'])`.
   - 100 % Eloquent, pas de `DB::` (agrégats via collections chargées ou `sum()`).

5. **`Portfolio\Observers\TransactionObserver`**
   - `creating` / `updating` → `$transaction->realized_gain = (CalculateRealizedGain)($transaction)`.
   - `created` / `updated` / `deleted` → `(ProjectHolding)($t->user_id, $t->asset_id, $t->wallet_id)`
     si `asset_id !== null` (sinon skip : mouvements cash).
   - Sur `updated`, si `asset_id` ou `wallet_id` a changé (`getOriginal`), reprojeter
     aussi l'ancienne paire.

6. **`DashboardDemoSeeder`** (réécriture) — crée des `Transaction` (achats, au moins
   une vente) au lieu d'insérer des `Holding` en direct. L'observer construit la
   projection. Rattaché au premier user (idempotent : purge les transactions du
   wallet démo avant de recréer).

## Flux

```
Transaction créée/modifiée/supprimée
  → TransactionObserver (creating/updating: realized_gain via CalculateRealizedGain)
  → TransactionObserver (created/updated/deleted: ProjectHolding)
     → recalcule qty + PRU depuis les transactions
     → upsert / delete holdings_projection (Holding)
  → GetPortfolioOverview lit holdings_projection → dashboard
```

## Règles de calcul

Projection, pour `(user, asset, wallet)` :
- `quantity = Σ achats.quantity − Σ ventes.quantity`
- `avg_cost (PRU) = Σ(achats.quantity × achats.unit_price) ÷ Σ achats.quantity`
- `quantity <= 0` → pas de ligne (position soldée)

Gain réalisé (vente uniquement) :
- `PRU_vente = Σ(achats≤date.quantity × unit_price) ÷ Σ achats≤date.quantity`
- `realized_gain = round((unit_price − PRU_vente) × quantity − fees, 2)`

## Décisions

- **Timing PRU du gain réalisé** : achats de date ≤ date de la vente (correct), pas
  « tous les achats » comme l'ancien code.
- **Cohérence** : Observer, donc la projection reste dérivée des transactions quelle
  que soit la source (seeder, tinker, tests, futur UI).
- **Reprojection** : sur create/update/delete. Update changeant asset/wallet →
  reprojette ancienne + nouvelle paire.
- **PRU non réinitialisé** : le PRU reste calculé sur l'ensemble des achats
  (comportement standard « prix de revient unitaire moyen »).
- **Pas de global scope auth** sur `Transaction` (cohérent avec le no-auth actuel).

## Développement en TDD

Ordre red → green → refactor :
1. `TransactionType` enum (+ test).
2. `Transaction` modèle + `TransactionFactory` (+ test relations/casts) — SANS l'attribut
   `#[ObservedBy]` d'abord, ou observer neutre, pour tester le modèle isolément.
3. `ProjectHolding` action (+ test : qty/PRU, delete si soldé).
4. `CalculateRealizedGain` action (+ test : null si achat, formule, PRU par date).
5. `TransactionObserver` + branchement `#[ObservedBy]` (+ feature test : buy crée la
   projection, sell réduit + realized_gain persisté, update/delete reprojette, changement
   de paire).
6. Réécriture `DashboardDemoSeeder` (+ test end-to-end : seed → overview non vide dérivé
   des transactions).

## Tests

- Unit : `CalculateRealizedGainTest`, `ProjectHoldingTest` (`app/Contexts/Portfolio/Actions/`)
- Modèle : `TransactionTest` (`app/Contexts/Portfolio/Models/`)
- Feature : `TransactionProjectionTest` (observer end-to-end), `DashboardDemoSeederTest` (mise à jour)
- Factories : `TransactionFactory` (états `buy`/`sell`), réutilise `WalletFactory`,
  `InstrumentFactory`, `User::factory()`.
