# Multi-Asset Architecture — Explicit Architecture for Extensible Investment Types

> Référence: https://herbertograca.com/2017/11/16/explicit-architecture-01-ddd-hexagonal-onion-clean-cqrs-how-i-put-it-all-together/

## 1. Problème actuel — le verrou `security_id`

Tout le système tourne autour de `securities`. Un seul FK `transactions.security_id` verrouille
l'architecture sur les actions/ETFs cotés en bourse. Pour ajouter Bitcoin, il faudrait modifier
la table `transactions` ET tous les services qui queryent par `security_id`.

```mermaid
graph LR
    T[transactions\nsecurity_id FK] -->|locked to| S[securities\nticker + ISIN\nstock-only]
    S -->|OHLCV prices| SP[security_prices\nopen/high/low/close/volume\nstock-only]
    S -->|sectors| SS[security_sectors\nGICS classification\nstock-only]
    S -->|data from| YF[YahooFinanceService\nstock-only adapter]

    style S fill:#ef4444,color:#fff
    style SP fill:#ef4444,color:#fff
    style SS fill:#ef4444,color:#fff
    style YF fill:#ef4444,color:#fff
    style T fill:#f59e0b,color:#fff
```

## 2. Architecture cible — Explicit Architecture (Hexagonal + DDD + CQRS)

```mermaid
graph TD
    subgraph Driving["Driving Side — UI"]
        UI["Filament UI"]
        CLI["Artisan CLI"]
    end

    subgraph Core["Application Core"]
        CMD["Commands\nRecordTransaction\nUpdateAssetPrices"]
        QRY["Queries\nGetValuation\nGetHoldings"]
        DOM["Domain\nAsset · Transaction · Wallet\nRealizedGainCalculator"]
        PP["Port: AssetPriceProvider"]
        AR["Port: AssetRepository"]
        TR["Port: TransactionRepository"]
        HR["Port: HoldingsReadModel"]
    end

    subgraph Driven["Driven Side — Infrastructure"]
        YF["YahooFinanceAdapter\nStock / ETF"]
        CG["CoinGeckoAdapter\nCrypto"]
        MP["ManualPriceAdapter\nRealEstate"]
        DB[("Database\nEloquent Repos")]
    end

    UI --> CMD
    UI --> QRY
    CLI --> CMD

    CMD --> DOM
    QRY --> DOM

    DOM --> PP
    DOM --> AR
    DOM --> TR
    DOM --> HR

    YF -.->|implements| PP
    CG -.->|implements| PP
    MP -.->|implements| PP
    DB -.->|implements| AR
    DB -.->|implements| TR
    DB -.->|implements| HR
```

## 3. Domain Model — `Asset` abstraction

```mermaid
classDiagram
    class Asset {
        <<AbstractAggregate>>
        +int id
        +string name
        +AssetType type
        +User user
        +prices() Collection~AssetPrice~
        +transactions() Collection~Transaction~
        +currentPrice() float
        +currentValuation() float
    }

    class Stock {
        +string ticker
        +string isin
        +sectors() Collection~Sector~
    }

    class Cryptocurrency {
        +string symbol
        +bool is_24h_market
    }

    class RealEstate {
        +string address
        +string type
    }

    class SavingsAccount {
        +string institution
        +float annual_rate
    }

    Asset <|-- Stock
    Asset <|-- Cryptocurrency
    Asset <|-- RealEstate
    Asset <|-- SavingsAccount

    class AssetPrice {
        <<ValueObject>>
        +int asset_id
        +Date date
        +float value
        +float open
        +float high
        +float low
        +float volume
    }

    class Transaction {
        <<Entity>>
        +int wallet_id
        +int asset_id
        +TransactionType type
        +float quantity
        +float unit_price
        +float fees
        +float realized_gain
    }

    class TransactionType {
        <<Enum>>
        Buy
        Sell
        Dividend
        Interest
        Fee
        Revaluation
    }

    Asset "1" --> "*" AssetPrice : prices
    Asset "1" --> "*" Transaction : transactions
    Transaction --> TransactionType
```

## 4. Port/Adapter — Price Provider par type d'actif

```mermaid
graph TB
    subgraph Core["Application Core"]
        UC["UpdateAssetPricesCommand"]
        PORT["AssetPriceProviderPort\n\ngetCurrentPrice(AssetId): float\ngetPriceHistory(AssetId, DateRange): PriceCollection\nsupports(AssetType): bool"]
        RESOLVER["AssetPriceProviderResolver\nfind adapter by AssetType"]
    end

    subgraph Adapters["Secondary Adapters"]
        YF["YahooFinanceAdapter\nsupports(Stock, ETF) → true\nfetchFromYahooAPI()"]
        CG["CoinGeckoAdapter\nsupports(Crypto) → true\nfetchFromCoinGeckoAPI()"]
        MP["ManualPriceAdapter\nsupports(RealEstate, Private) → true\nreadsFromUserInput()"]
    end

    subgraph External["External APIs"]
        YAPI["Yahoo Finance API\nOHLCV data"]
        CGAPI["CoinGecko API\n24/7 crypto prices"]
        MANUAL["Manual Entry\nUser-provided values"]
    end

    UC --> RESOLVER
    RESOLVER --> PORT
    YF -.->|implements| PORT
    CG -.->|implements| PORT
    MP -.->|implements| PORT
    YF --> YAPI
    CG --> CGAPI
    MP --> MANUAL
```

## 5. CQRS — Séparation Command / Query

```mermaid
graph LR
    subgraph Commands["Commands (Write Side)"]
        C1["RecordTransaction\n→ Transaction entity\n→ RealizedGainCalculator\n→ TransactionCreated event"]
        C2["UpdateAssetPrices\n→ AssetPriceProviderPort\n→ PriceUpdated event"]
        C3["CreateAsset\n→ Asset aggregate\n→ AssetCreated event"]
    end

    subgraph Events["Domain Events"]
        E1["TransactionCreated"]
        E2["PriceUpdated"]
        E3["AssetCreated"]
    end

    subgraph Projections["Read Model Projections (Query Side)"]
        P1["HoldingsProjection\nasset_id → quantity\nupdated on TransactionCreated"]
        P2["ValuationProjection\nasset_id → current_value\nupdated on PriceUpdated"]
        P3["PerformanceProjection\nTWR, CAGR, volatility\nrecomputed async"]
    end

    subgraph Queries["Queries (Read Side)"]
        Q1["GetPortfolioValuation\nreads ValuationProjection\nnever touches transactions"]
        Q2["GetHoldings\nreads HoldingsProjection\nnever touches transactions"]
        Q3["GetRebalancingSuggestions\nreads HoldingsProjection + prices\nno direct ORM query"]
    end

    C1 -->|dispatches| E1
    C2 -->|dispatches| E2
    C3 -->|dispatches| E3

    E1 -->|updates| P1
    E2 -->|updates| P2
    E1 -->|triggers| P3

    Q1 --> P2
    Q2 --> P1
    Q3 --> P1
```

## 6. Bounded Contexts cibles

```mermaid
graph TD
    subgraph Shared["Shared Kernel"]
        DomainEvent["DomainEvent (base)"]
        MoneyVO["Money (Value Object)"]
        DateRange["DateRange (Value Object)"]
        AssetType["AssetType (Enum)\nStock / ETF / Crypto / RealEstate / Bond / Savings"]
    end

    subgraph UserCtx["User Context"]
        User["User aggregate"]
    end

    subgraph AssetCtx["Asset Context (replaces Security)"]
        Asset["Asset aggregate"]
        AssetPrice["AssetPrice"]
        AssetPriceProviderPort["AssetPriceProviderPort"]
        subgraph AssetAdapters["Infrastructure Adapters"]
            YFAdapter["YahooFinanceAdapter"]
            CGAdapter["CoinGeckoAdapter"]
            ManualAdapter["ManualPriceAdapter"]
        end
    end

    subgraph PortfolioCtx["Portfolio Context"]
        Wallet["Wallet aggregate"]
        Transaction["Transaction entity"]
        RGCalc["RealizedGainCalculator"]
        HoldingsProjection["HoldingsProjection (read model)"]
    end

    subgraph AnalyticsCtx["Analytics Context"]
        Volatility["VolatilityCalculator"]
        Rebalancing["RebalancingCalculator"]
        Simulation["SimulationEngine"]
        ValuationProjection["ValuationProjection (read model)"]
    end

    UserCtx --> PortfolioCtx
    UserCtx --> AssetCtx

    AssetCtx -->|AssetPriceProviderPort| AssetAdapters
    PortfolioCtx -->|asset_id only, no model import| AssetCtx
    AnalyticsCtx -->|reads projections only| PortfolioCtx
    AnalyticsCtx -->|reads projections only| AssetCtx

    Shared -.->|used by all| AssetCtx
    Shared -.->|used by all| PortfolioCtx
    Shared -.->|used by all| AnalyticsCtx
```

## 7. Schema DB — migration vers multi-asset

### AS-IS (stock-locked)

```mermaid
erDiagram
    securities {
        int id PK
        string name
        string ticker
        string isin
        int user_id FK
    }
    security_prices {
        int id PK
        int security_id FK
        date date
        float open
        float high
        float low
        float close
        float volume
    }
    transactions {
        int id PK
        int security_id FK
        int wallet_id FK
        int user_id FK
        string type
        float quantity
        float unit_price
        float fees
        float realized_gain
    }

    securities ||--o{ security_prices : "has prices"
    securities ||--o{ transactions : "transacted via"
```

### TO-BE (multi-asset)

```mermaid
erDiagram
    assets {
        int id PK
        string name
        string type "Stock|ETF|Crypto|RealEstate|Bond|Savings"
        int user_id FK
    }
    asset_details_stocks {
        int asset_id FK
        string ticker
        string isin
    }
    asset_details_crypto {
        int asset_id FK
        string symbol "BTC, ETH - provider-agnostic"
    }
    asset_prices {
        int id PK
        int asset_id FK
        date date
        float value
        float open "nullable - OHLCV stocks only"
        float high "nullable - OHLCV stocks only"
        float low "nullable - OHLCV stocks only"
        float volume "nullable - OHLCV stocks only"
    }
    transactions {
        int id PK
        int asset_id FK
        int wallet_id FK
        int user_id FK
        string type "Buy|Sell|Dividend|Interest|Fee|Revaluation"
        float quantity
        float unit_price
        float fees
        float realized_gain
    }
    holdings_projection {
        int asset_id FK
        int wallet_id FK
        int user_id FK
        float quantity
        float avg_cost
        datetime updated_at
    }

    assets ||--o| asset_details_stocks : "stock details"
    assets ||--o| asset_details_crypto : "crypto details"
    assets ||--o{ asset_prices : "price history"
    assets ||--o{ transactions : "transactions"
    assets ||--o{ holdings_projection : "current holdings"
```

## 8. Ajout d'un nouveau type — exemple Bitcoin

Pour ajouter Bitcoin (Cryptocurrency), avec la cible architecture:

```mermaid
sequenceDiagram
    participant Dev as Developer
    participant Asset as Asset Context
    participant Port as AssetPriceProviderPort
    participant Adapter as CoinGeckoAdapter
    participant Portfolio as Portfolio Context
    participant UI as Filament UI

    Dev->>Asset: 1. Create CryptoAsset model extends Asset
    Dev->>Asset: 2. Add asset_details_crypto migration
    Dev->>Adapter: 3. Implement CoinGeckoAdapter implements AssetPriceProviderPort
    Dev->>Port: 4. Register CoinGeckoAdapter for AssetType Crypto
    Dev->>UI: 5. Add Crypto option to asset type selector
    Note over Portfolio: Transaction, Wallet, RealizedGainCalculator need ZERO changes
    Note over Asset: Only 3 new files: Model + Migration + Adapter
```

**Fichiers à créer (uniquement):**
1. `app/Domains/Asset/Models/CryptoAsset.php` — extends `Asset`
2. `database/migrations/xxxx_create_asset_details_crypto_table.php`
3. `app/Domains/Asset/Infrastructure/Adapters/CoinGeckoAdapter.php` — implements `AssetPriceProviderPort`

**Fichiers à modifier (zéro ou minime):**
- `AppServiceProvider` — enregistrer `CoinGeckoAdapter` pour `AssetType::Crypto`
- `AssetType` enum — ajouter `Crypto` case
- `MarketCalendar` — remplacer par logique par-adapter (crypto = 24/7, stocks = Mon-Fri)

## 9. Roadmap de migration incrémentale

> **Règle TDD appliquée à chaque phase** — Red → Green → Refactor.
> Chaque étape se termine uniquement quand les 3 gates passent (voir section 9.1).

```mermaid
graph TD
    subgraph Phase6["Phase 6 — Events"]
        P6A["Wire TransactionCreated dispatch"]
        P6B["Wire PriceUpdated dispatch"]
        P6C["Wire PortfolioRebalanced dispatch"]
    end

    G6{"Gate 6\nPest + PHPStan + Pint"}

    subgraph Phase7["Phase 7 — Contrats"]
        P7A["Binder SecurityRepositoryInterface\nSecurityPriceRepositoryInterface\nTransactionRepositoryInterface"]
        P7B["Remplacer appels ORM directs\npar contrats dans services"]
        P7C["Fixer VolatilityCalculating\nWallet to int walletId"]
    end

    G7{"Gate 7\nPest + PHPStan + Pint"}

    subgraph Phase8["Phase 8 — Asset abstraction"]
        P8A["Extraire Asset aggregate\ndu Security domain"]
        P8B["Migration asset_prices\nremplace security_prices"]
        P8C["Migration transactions\nsecurity_id vers asset_id"]
    end

    G8{"Gate 8\nPest + PHPStan + Pint"}

    subgraph Phase9["Phase 9 — Ports & Projections"]
        P9A["AssetPriceProviderPort\nYahooFinanceAdapter stocks"]
        P9B["HoldingsProjection read model\nmis a jour via TransactionCreated"]
        P9C["RebalancingOrchestrator\nlit projection, plus ORM Transaction"]
    end

    G9{"Gate 9\nPest + PHPStan + Pint"}

    subgraph Phase10["Phase 10 — Bitcoin"]
        P10A["CoinGeckoAdapter\nimplements AssetPriceProviderPort"]
        P10B["CryptoAsset model\nasset_details_crypto migration"]
    end

    Phase6 --> G6 --> Phase7 --> G7 --> Phase8 --> G8 --> Phase9 --> G9 --> Phase10

    style Phase6 fill:#3b82f6,color:#fff
    style Phase7 fill:#6366f1,color:#fff
    style Phase8 fill:#8b5cf6,color:#fff
    style Phase9 fill:#a855f7,color:#fff
    style Phase10 fill:#10b981,color:#fff
    style G6 fill:#f59e0b,color:#fff
    style G7 fill:#f59e0b,color:#fff
    style G8 fill:#f59e0b,color:#fff
    style G9 fill:#f59e0b,color:#fff
```

### 9.1 Gate de validation — obligatoire entre chaque phase

Aucune phase suivante ne démarre tant que les 3 commandes ne passent pas en vert.

```bash
# 1. Tests Pest — tous les tests du domaine modifié + régressions globales
php artisan test --compact

# 2. PHPStan niveau 2 — aucun type error, aucune propriété non typée
vendor/bin/phpstan analyse app/Domains/ --level=2

# 3. Pint — formatage propre
vendor/bin/pint --dirty --format agent
```

**TDD par étape:**
- Écrire le test Pest **avant** d'implémenter (Red)
- Implémenter jusqu'à ce que le test passe (Green)
- Refactorer sans casser les tests (Refactor)
- Committer uniquement quand Gate passe

**Commandes utiles par scope:**

| Scope | Commande |
|-------|----------|
| Portfolio uniquement | `php artisan test --compact tests/Feature/Domains/Portfolio/` |
| Analytics uniquement | `php artisan test --compact tests/Feature/Domains/Analytics/` |
| Filtre sur un test | `php artisan test --compact --filter=NomDuTest` |
| PHPStan domaine précis | `vendor/bin/phpstan analyse app/Domains/Portfolio/ --level=2` |

## 10. Contrats existants à étendre (pas réécrire)

| Contrat existant | Statut | Action Phase 7 |
|-----------------|--------|---------------|
| `SecurityRepositoryInterface` | ✅ Défini, ✅ bindé (Phase 7A) | Utilisable en Phase 7B+ |
| `SecurityPriceRepositoryInterface` | ✅ Défini, ✅ bindé (Phase 7A) | Utilisable en Phase 7B+ |
| `TransactionRepositoryInterface` | ✅ Défini, ✅ bindé (Phase 7A) | Utilisable en Phase 7B+ |
| `PriceRefreshing` | ✅ Défini, ✅ bindé | Renommer `AssetPriceProviderPort`, ajouter `supports(AssetType)` |
| `VolatilityCalculating` | ✅ Défini, ✅ bindé | Signature change `Wallet → int walletId` (TBD Phase 7C) |
| `Rebalancing` | ✅ Défini, ✅ bindé | Aucun changement nécessaire (pure math) |

## 11. Phase 7 — Résultats et blockers

### Phase 7A ✅ Complète
- Bindings ajoutés pour SecurityRepository, SecurityPriceRepository, TransactionRepository
- Status: Tous les tests passent, prêt pour Phase 7B+

### Phase 7B ⏸️ Blocker: Contexte utilisateur
Refactoriser 7 services vers injection repository révèle **problème critique**:
- `forWallet(walletId, userId)` et `forSecurity(securityId, userId)` requièrent `userId`
- Services comme VolatilityCalculator, PortfolioPerformanceService reçoivent wallet mais pas userId
- `auth()->id()` retourne `null` en tests → binde crash

**Services affectés:**
1. RealizedGainCalculator — **résolu** (transaction.user_id disponible)
2. SingleSecurityStatsProvider — **complexe** (besoin userId pour forSecurity)
3. VolatilityCalculator — **blocké** (pas userId context, signature change TBD)
4. PortfolioPerformanceCalculator — **blocké** (idem)
5. PortfolioPerformanceService — **blocké**
6. DashboardDataProvider — **blocké**
7. YahooFinanceService — **peut rester ORM** (aucun userId needed)

### Solution recommandée Phase 7B
**Option 1 (Pragmatique)**: Garder ORM directs pour services sans userId context
- Services purs (VolatilityCalculator, PortfolioPerformanceCalculator) → queryBuilder direct
- Services avec transaction context (RealizedGainCalculator) → repository injection
- Phase 7 focus: RealizedGainCalculator seulement

**Option 2 (Refactor majeur)**: Restructurer services pour passer userId explicitement
- Requires: Modifier signature tous les services
- Impact: Cascading changes à Filament pages, commands, tests
- Timeline: Phase 8+

**Option 3 (À explorer)**: Request-scoped UserId service
- `UserId $userId` service injectable, fallback auth()->id() en production
- Permet test fixture override
- Moins intrusif que Option 2

### Décision Phase 8
Recommandation: **Option 1** court-terme (complète Phase 7 partiel)
- Phase 7B.1: RealizedGainCalculator + tests ✅
- Phase 7B.2: Pause, marker pour Phase 8 avec Option 2 ou 3
- Phase 8: Addresser Commands (FetchSecurityPricesCommand, etc) — plus simples que services
