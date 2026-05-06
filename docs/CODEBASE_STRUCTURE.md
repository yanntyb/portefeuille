# Structure de la codebase — Observations & Recommandations

## État actuel après refactoring S1–S7

### Ce qui fonctionne bien

**1. Séparation Pages/Widgets/Resources**
- Pages Filament clairement délimitées (AccountPage, WalletPage, Dashboard)
- Widgets reposent sur les pages pour les contextes (via props `$record`, `$walletId`)
- Resources (Securities, Wallets) découpent bien les CRUD par domaine

**2. Services avec logique métier**
- `VolatilityCalculator` : calculs purs (volatilité annualisée, pondérée)
- `DashboardDataProvider` : cache instance-level per request (scoped)
- `PriceRefreshService` : extraction de la fetch de prix
- `PortfolioPerformanceCalculator` : TWR, valuations historiques
- Injectables via `app(ClassName::class)` — pattern cohérent Octane-safe

**3. Models & Scopes**
- `Security`, `Wallet`, `Transaction` avec relationships explicites
- Scopes : `forWallet()`, `forAuth()` — filtrage à la source
- Enums pour les types : `TransactionType`, `CurrencyModificationUnit`, `FeeScope`, `FrequencyUnit`

**4. Traits pour réutilisation**
- `ComputesSingleSecurityStats` : logique Transaction query partagée par 5 widgets
- `HasTableStore`, `ExposesTableToWidgets` : behaviors Filament isolés
- Pattern Template Method dans `EditSecurity` → `EditWalletSecurity` (via `getWalletId()`)

**5. Vues Blade partagées**
- `gain-stats-overview.blade.php` : utilisée par 3 widgets (Dashboard, Wallet, Security) avec `@isset` conditionnels
- Réduit duplication, centralise UI

---

## Complexités & Frictions

### 1. Pages abstraites trop chargées

`AccountPage` (abstract) fait trop :
```
- scopedSecuritiesQuery()          ← requête
- computeSecurityVisibility()      ← état UI
- getTotalValuation()              ← calcul
- computeAnnualizedReturn()        ← calcul
- computePortfolioVolatility()     ← calcul (délégué à Service)
- toggleSecurity()                 ← event dispatch
- refreshPrices()                  ← prix
- getFormattedValuation()          ← formatting
- content() override + widgets     ← UI routing
```

**Conséquence** : Difficile de tester, mixte de responsabilités (state, queries, calculations, UI routing).

**Amélioration suggérée** :
```php
// Extraire AccountPageDataProvider service
class AccountPageDataProvider {
    public function getValuation(Wallet $wallet): float
    public function getAnnualizedReturn(Wallet $wallet): float
    public function getPortfolioVolatility(Wallet $wallet): float
    public function getSecurityVisibility(Wallet $wallet): array
}

// AccountPage devient lean
class AccountPage {
    public function __construct(private AccountPageDataProvider $provider) { }
    
    protected function getTotalValuation(): float {
        return $this->provider->getValuation($this->wallet);
    }
}
```

### 2. Naming implicite

- `currentPrice` (relation) vs `latestPrice` (relation) — confus, voir `Security` model
- `refreshPrices()` vs `loadPrices()` — même concept, noms différents (avant S6 ils étaient même du code dupliqué)
- `total_invested` vs `totalInvested` — snake_case DB vs camelCase PHP sans DTO clair
- `currentValuation()` method : "current" = basé sur `latestPrice`, mais quand c'est basé sur `currentPrice`?

**Impact** : Junior devs peuvent faire des erreurs, chercher où est telle data.

**Amélioration** :
- Créer DTO explicite :
```php
class SecurityValuation {
    public function __construct(
        public float $quantity,
        public float $pricePerUnit,
        public float $totalValue,
        public \DateTime $priceDate,
    ) {}
}
```

### 3. Logique métier dans Widgets

5 SingleSecurity* widgets calculent des stats indépendamment (avant S5) :
- Chacun appelle `computeStats()` → Transaction query
- Pas de cache across components
- Répète calculations même si données inchangées

**Solution appliquée (S5)** : `SingleSecurityStatsProvider` scoped — bon fix tactique.

**Solution long terme** : Computed properties Livewire 4 avec cache intelligent, ou refetch stratégique via Livewire events.

### 4. Pas de Domain Layer clair

Logique métier éparpillée :
- Calculs de volatilité → `VolatilityCalculator` ✓
- Calculs de valuation → éparpillés (Models, Services, Pages, Widgets)
- Règles de frais → schema imbriquée dans Actions, `WalletFeesSchema`
- Règles de visibilité → `computeSecurityVisibility()` dans AccountPage

**Amélioration** :
```
app/Domain/
  ├── Portfolio/
  │   ├── PortfolioCalculator (centralise all portfolio maths)
  │   ├── PortfolioValuation (VO)
  │   └── PortfolioPerformance (VO)
  ├── Security/
  │   ├── SecurityValuation (VO)
  │   └── SecurityStats (VO)
  └── Fees/
      ├── FeeSchedule (value object)
      └── FeeCalculator (logique)
```

Puis Services orchestrent Domain + Models.

### 5. Testing friction

- `AccountPage` abstract + monolithic → mock `Wallet`, `Security`, `Transaction` separately
- `ComputesSingleSecurityStats` trait → hard to unit test in isolation (requires full Livewire component)
- No clear seams for dependency injection (Pages don't support PHP constructor injection with Livewire)

**Workaround actuel** : Tout dans Feature tests avec DB réel.

**Amélioration** : Extraire logique dans Services testables, Pages se limitent à orchestration.

---

## Recommandations par priorité

### Tier 1 (Court terme — avant roadmap Tier 1)

1. **Créer `AccountPageDataProvider`**
   - Extrait `getTotalValuation()`, `getAnnualizedReturn()`, `computePortfolioVolatility()`, `getSecurityVisibility()`
   - AccountPage devient thin orchestrator
   - Chaque méthode testable en unit test

2. **Standardiser naming**
   - Choisir : `currentPrice` (relation) OU `latestPrice` (relation) — utiliser un seul
   - Renommer `refreshPrices()` → `loadAndRefreshPrices()` ou uniformiser vs `loadPrices()`
   - Documentez la distinction `total_invested` (cumul buy cost + fees) vs `total_realized_gain`

3. **DTO pour valuations**
   ```php
   class SecurityValuation {
       public float $quantity;
       public float $currentPrice;
       public float $totalValue;
       public \DateTime $priceDate;
   }
   ```
   Utilisable partout (Widgets, Services, Exports) — une source de vérité

### Tier 2 (Moyen terme — pendant roadmap Tier 1)

4. **Domain Layer pour maths**
   - `Domain/Portfolio/PortfolioCalculator` : volatilité, CAGR, MDD, Sharpe, etc.
   - `Domain/Security/SecurityStats` : consolidate computeStats
   - Value Objects pour les résultats

5. **Livewire Actions → Service methods**
   - `configureFeesAction()` → `UpdateWalletFeesService`
   - Action devient thin wrapper (form binding + dispatch)
   - Service contient la logique, testable

6. **Query optimization doc**
   - Liste des N+1 connus et fixes appliquées (S1, S4)
   - Pattern : utiliser `DashboardDataProvider` vs queries inline
   - Guidelines pour Eager Loading (`.with('relation')`)

### Tier 3 (Long terme)

7. **Repository pattern** (optionnel)
   - Si queries deviennent encore plus complexes, centralize dans Repositories
   - Actuellement Eloquent scope suffisent

8. **Event sourcing pour audit**
   - Priceless securities, frais, transactions — créer AuditLog events
   - Utile pour: debug, compliance, undo

---

## Patterns réussis à préserver

✅ **Scoped Bindings** (`app()->scoped()`) — Octane-safe caching per-request  
✅ **Service Injection via `app()` helper** — Compatible avec Livewire composants  
✅ **Shared Blade views + `@isset` conditionals** — DRY sans over-abstraction  
✅ **Template Method (EditSecurity → EditWalletSecurity)** — Minimal duplication  
✅ **Traits for cross-cutting concerns** — Reuse without hierarchy bloat  
✅ **Enums pour types** — Type-safe, self-documenting  

---

## Lisibilité globale : 6/10

**Avant refactoring (S1–S4)** : 5/10  
- Duplication spread, N+1 hidden, unclear ownership

**Après refactoring (S1–S7)** : 6/10  
- Services extracted, cache fixed, Repeater shared
- Still: pages too heavy, naming inconsistent, no Domain layer

**Potentiel post-Tier2** : 8/10  
- Thin Pages, Domain logic extracted, clear data flows

---

## Pour le prochain dev

1. **Lire** : `docs/REFACTORING.md` (why we fixed S1–S7)
2. **Pattern** : New feature = Service + DTO + maybe a Page component or Resource
3. **Queries** : Always use `DashboardDataProvider` for dashboards, `->with()` for eager load
4. **Tests** : Feature tests + DB are primary; unit test Services
5. **Ask** : If you're duplicating code in 2 places, extract before 3

---

*Écrit après refactoring S1–S7. Basé sur patterns Filament v5, Laravel 12, Livewire 4.*
