# Architecture DDD / Hexagonale

Ce document décrit l'architecture en couches de l'application : organisation en **bounded contexts**, application des principes **ports & adapters** (architecture hexagonale) et mécanisme de **wiring** de l'injection de dépendances (DI).

## 1. Principes

L'application est structurée en **contextes délimités** (bounded contexts) sous `app/Contexts/`, chacun isolé et responsable d'un sous-domaine métier. Les contextes identifiés :

| Contexte | Chemin | Statut |
| --- | --- | --- |
| Market | `app/Contexts/Market/` | Actif (instruments, prix, secteurs) |
| Portfolio | `app/Contexts/Portfolio/` | Actif (transactions, projection des positions) |
| Valuation | `app/Contexts/Valuation/` | Actif (séries de valorisation, performances) |
| Income | `app/Contexts/Income/` | Actif (revenus par origine : dividendes, loyers) |
| RealEstate | `app/Contexts/RealEstate/` | Actif (biens, baux, prêts, charges) |
| InstrumentView | `app/Contexts/InstrumentView/` | Actif (lecture : catalogue et fiche d'un titre) |
| Wealth | `app/Contexts/Wealth/` | Actif (lecture : patrimoine toutes classes confondues) |
| Identity | `app/Contexts/Identity/` | Stub (User/Role, sans contrats) |

Les quatre premiers portent des `Models/` ; `InstrumentView` et `Wealth` n'en ont pas — ce sont des
contextes de **lecture**, qui n'agrègent que ce que leurs ports leur rendent.

### Concepts directeurs

- **Bounded contexts** : chaque contexte expose son propre modèle métier, ses interfaces et ses implémentations. Aucune fuite de détails d'un contexte vers un autre.
- **Isolation** : la logique d'un contexte ne dépend que de ses propres abstractions (Contracts / Ports), jamais directement des implémentations d'un autre contexte.
- **Shared kernel** : le code transverse réellement partagé vit sous `app/Shared/`. Exemple actuel : `app/Shared/Python/`, noyau d'exécution de scripts Python réutilisable par n'importe quel contexte.
- **Ports & adapters** (hexagonal) : le cœur métier définit des interfaces (ports), et les détails techniques (Eloquent, API externes) sont des **adapters** qui implémentent ces interfaces. Le cœur ne connaît jamais l'implémentation concrète.
- **Inversion de dépendance** : les dépendances pointent vers les abstractions. Le wiring concret (interface → implémentation) est centralisé dans les `*Provider.php` et résolu au démarrage via le conteneur DI de Laravel.

## 2. Convention de structure d'un contexte

Un contexte suit la convention de dossiers suivante. Tous les dossiers ne sont pas systématiquement présents (selon la maturité du contexte).

| Dossier | Rôle |
| --- | --- |
| `Models/` | Entités Eloquent (persistance ORM, relations, casts, factories). Représentent le modèle de domaine persisté. |
| `Datas/` | DTO (Data Transfer Objects) readonly. Transportent les données entre adapters externes et le cœur, découplant les formats externes du modèle persisté. |
| `Enums/` | Énumérations métier (valeurs admises). Pures, sans dépendance framework ; méthodes de présentation (`getLabel`/`getColor`/`getIcon`) retournant des primitives. |
| `Contracts/` | Interfaces de **persistance interne** (repositories). Définissent le contrat avec la base de données locale. |
| `Ports/` | Interfaces de **fournisseurs externes** (outbound). Définissent le contrat avec des sources externes (API Yahoo, ou la DB comme alternative). |
| `Infrastructure/` | **Adapters** : implémentations concrètes des Contracts (Eloquent) et des Ports (API / DB). |
| `Factories/` | Factories Eloquent pour générer des fixtures dans les tests. Non liées à l'hexagone, dédiées au testing. |
| `*Provider.php` | Service provider du contexte : orchestre le binding DI (interface → implémentation) via une méthode statique `registers()`. |

### Exemple : contexte Market

```
app/Contexts/Market/
├── Contracts/
│   ├── InstrumentRepositoryContract.php
│   └── PriceRepositoryContract.php
├── Ports/
│   ├── InstrumentProviderPort.php
│   ├── PriceProviderPort.php
│   └── SectorProviderPort.php
├── Infrastructure/
│   ├── EloquentInstrumentRepository.php
│   ├── EloquentPriceRepository.php
│   ├── DatabaseAssetPriceAdapter.php
│   └── YahooFinanceAdapter.php
├── Models/
│   ├── Instrument.php
│   ├── Price.php
│   └── SectorAllocation.php
├── Datas/
│   ├── InstrumentData.php
│   ├── PriceData.php
│   ├── AssetPriceData.php
│   └── SectorAllocationData.php
├── Enums/
│   ├── InstrumentType.php
│   └── Sector.php
├── Factories/
│   ├── InstrumentFactory.php
│   ├── PriceFactory.php
│   └── SectorAllocationFactory.php
└── MarketProvider.php
```

### Exemple : contexte Wealth

Contexte de lecture pure : ni `Models/`, ni `Contracts/`, ni migration. Il ne sait rien des
transactions ni des baux — il additionne ce que quatre ports lui rendent, chacun implémenté par un
adaptateur qui appelle l'action du contexte propriétaire de la donnée.

```
app/Contexts/Wealth/
├── Ports/
│   ├── HoldingsPort.php            → valeur et prix de revient des titres
│   ├── SecuritiesSeriesPort.php    → série des titres dans le temps
│   ├── RealEstatePort.php          → patrimoine net, cash sorti et série de l'immobilier
│   └── IncomePort.php              → dividendes des douze derniers mois, mensualisés
├── Infrastructure/
│   ├── PortfolioHoldings.php       → Portfolio\Actions\GetPortfolioOverview
│   ├── ValuationSeries.php         → Valuation\Actions\BuildEvolutionSeries
│   ├── RealEstateFinancials.php    → RealEstate\Actions (résumé, cash sorti, série nette)
│   └── DividendIncome.php          → Income\Actions\GetIncomeSummary, filtré Dividend
├── Actions/
│   ├── GetWealthOverview.php       → le grand chiffre et ses classes d'actif
│   ├── BuildWealthSeries.php       → titres et immobilier empilés sur une grille commune
│   └── GetWealthIncome.php         → dividendes + locatif net, en un revenu mensuel
├── Services/
│   └── SeriesAligner.php           → recale deux séries de granularités différentes
├── Datas/
│   ├── WealthOverviewData.php  AssetClassData.php  ClassSnapshotData.php
│   ├── WealthSeriesData.php    ClassSeriesData.php
│   └── WealthIncomeData.php
├── Http/
│   └── DashboardController.php     → route `/`
└── WealthProvider.php
```

**Pourquoi `Wealth` et non `WealthView`.** `Valuation` et `Income` sont déjà des contextes dérivés
sans suffixe. `InstrumentView` porte le sien uniquement parce que `Market\Models\Instrument`
occupait déjà le nom ; aucun modèle ne s'appelle `Wealth`, donc le suffixe n'aurait rien à
désambiguïser.

## 3. Distinction clé : Contracts vs Ports vs Adapters vs Datas vs Models

C'est la distinction structurante de l'architecture. Deux familles d'interfaces coexistent :

- **Contracts** = contrat de **persistance interne** (la base de données locale, source de vérité).
- **Ports** = contrat avec un **fournisseur externe** (outbound : API Yahoo, ou la DB exposée comme fournisseur).

| Concept | Dossier | Nature | Rôle | Exemples |
| --- | --- | --- | --- | --- |
| **Contracts** | `Contracts/` | Interface | Persistance interne (repository sur la DB locale) | `InstrumentRepositoryContract`, `PriceRepositoryContract` |
| **Ports** | `Ports/` | Interface | Fournisseur externe (outbound) | `InstrumentProviderPort`, `PriceProviderPort`, `SectorProviderPort` |
| **Adapters** | `Infrastructure/` | Classe | Implémentation concrète d'un Contract ou d'un Port | `EloquentInstrumentRepository`, `EloquentPriceRepository`, `DatabaseAssetPriceAdapter`, `YahooFinanceAdapter` |
| **Datas** | `Datas/` | DTO readonly | Transfert de données externes vers le domaine | `InstrumentData`, `PriceData`, `AssetPriceData`, `SectorAllocationData` |
| **Models** | `Models/` | Entité Eloquent | Modèle persisté (ORM, relations, casts) | `Instrument`, `Price`, `SectorAllocation` |

### Points notables

- Un adapter peut implémenter **un Contract** (`EloquentInstrumentRepository` → `InstrumentRepositoryContract`) ou **un Port** (`YahooFinanceAdapter` → 3 ports).
- `DatabaseAssetPriceAdapter` est un adapter outbound qui **implémente** `PriceProviderPort` mais **délègue** à `PriceRepositoryContract` : la base locale devient la source de vérité du fournisseur de prix par défaut.
- `YahooFinanceAdapter` implémente **trois ports** (`InstrumentProviderPort`, `PriceProviderPort`, `SectorProviderPort`) dans une seule classe : couplage réduit côté wiring, complexité concentrée dans l'adapter.
- Les **Datas** servent de pont : `AssetPriceData::fromPriceData()` adapte un `PriceData` (issu d'un fournisseur externe) pour la persistance (conversion float → string décimale, normalisation des valeurs manquantes via fallback sur `close`, ajout de timestamps).

## 4. Mécanisme de wiring DI

### Pattern `XxxProvider::registers()`

Chaque contexte expose un provider avec une méthode **statique** `registers(Application $app, ...)` au lieu du cycle `register()/boot()` standard. Cette méthode reçoit en paramètres les **class-strings** des implémentations à binder, ce qui permet de swapper les implémentations à la composition (test vs prod).

`AppServiceProvider::register()` est le point d'entrée unique : il appelle les `registers()` de chaque contexte.

| Provider | Bindings effectués |
| --- | --- |
| `PythonProvider` | `PythonRunner` → `ProcessPythonRunner` (singleton) |
| `MarketProvider` | `InstrumentRepositoryContract` → `EloquentInstrumentRepository`<br>`PriceRepositoryContract` → `EloquentPriceRepository`<br>`PriceProviderPort` → `DatabaseAssetPriceAdapter` |
| `WealthProvider` | `HoldingsPort` → `PortfolioHoldings`<br>`SecuritiesSeriesPort` → `ValuationSeries`<br>`IncomePort` → `DividendIncome`<br>`RealEstatePort` → `RealEstateFinancials` (**scoped** : une instance par requête, partagée par les trois actions) |
| `IdentityProvider` | Stub (aucun binding) |
| `PortfolioProvider` | Stub (aucun binding) |

Ordre d'enregistrement des providers (via `bootstrap/providers.php`) : `AppServiceProvider` → `EventServiceProvider`.

Appels concrets dans `AppServiceProvider::register()` :

- `PythonProvider::registers($this->app, ProcessPythonRunner::class)`
- `MarketProvider::registers($this->app, EloquentInstrumentRepository::class, EloquentPriceRepository::class, DatabaseAssetPriceAdapter::class)`

`AppServiceProvider::boot()` force par ailleurs la locale Carbon à `fr`.

### Diagramme des bindings actuels

```mermaid
flowchart TD
    Bootstrap["bootstrap/app.php"] --> AppSP["AppServiceProvider"]
    Bootstrap --> EventSP["EventServiceProvider (vide)"]

    AppSP -->|"registers()"| PythonSP["PythonProvider"]
    AppSP -->|"registers()"| MarketSP["MarketProvider"]

    PythonSP -->|bind singleton| PythonRunner["PythonRunner (interface)"]
    ProcessPR["ProcessPythonRunner"] -.implements.-> PythonRunner

    MarketSP -->|bind| InstrRepo["InstrumentRepositoryContract"]
    MarketSP -->|bind| PriceRepo["PriceRepositoryContract"]
    MarketSP -->|bind| PricePort["PriceProviderPort"]

    EloquentIR["EloquentInstrumentRepository"] -.implements.-> InstrRepo
    EloquentPR["EloquentPriceRepository"] -.implements.-> PriceRepo
    DbAdapter["DatabaseAssetPriceAdapter"] -.implements.-> PricePort
    DbAdapter -->|delegue a| PriceRepo

    YahooAdapter["YahooFinanceAdapter"] -->|injecte| PythonRunner
```

> Note : `PriceProviderPort` est bindé par défaut sur `DatabaseAssetPriceAdapter` (la DB est source de vérité). `YahooFinanceAdapter` reste disponible comme implémentation alternative des ports mais n'est pas bindé par défaut.

## 5. Diagramme de classes — ports, contrats et implémentations

Ce diagramme se concentre sur la structure hexagonale du contexte Market : les
interfaces (Contracts internes + Ports externes), leurs implémentations et le
binding via `MarketProvider`. Le diagramme de classes complet du domaine
(Models, Datas, Enums et leurs relations) est documenté dans
[`market-context.md`](market-context.md).

```mermaid
classDiagram
    class InstrumentRepositoryContract {
        <<interface>>
        +findById(int) Instrument
        +findByType(InstrumentType) Collection
        +save(Instrument) void
    }
    class PriceRepositoryContract {
        <<interface>>
        +latestForAsset(int) Price
        +forAssetSince(int, Carbon) Collection
        +forAssets(array, Carbon) Collection
    }
    class InstrumentProviderPort {
        <<interface>>
        +findBySymbol(string, InstrumentType) InstrumentData
        +supports(InstrumentType) bool
    }
    class PriceProviderPort {
        <<interface>>
        +getCurrentPrice(int) float
        +getPriceHistory(int, string, string) Collection
        +supports(InstrumentType) bool
    }
    class SectorProviderPort {
        <<interface>>
        +getSectorAllocations(string, InstrumentType) array
        +supports(InstrumentType) bool
    }

    class EloquentInstrumentRepository
    class EloquentPriceRepository
    class DatabaseAssetPriceAdapter {
        -PriceRepositoryContract repository
    }
    class YahooFinanceAdapter {
        -PythonRunner python
    }
    class MarketProvider {
        +registers(app, instrumentRepo, priceRepo, priceProvider) void
    }

    InstrumentRepositoryContract <|.. EloquentInstrumentRepository
    PriceRepositoryContract <|.. EloquentPriceRepository
    PriceProviderPort <|.. DatabaseAssetPriceAdapter
    InstrumentProviderPort <|.. YahooFinanceAdapter
    PriceProviderPort <|.. YahooFinanceAdapter
    SectorProviderPort <|.. YahooFinanceAdapter

    DatabaseAssetPriceAdapter --> PriceRepositoryContract : delegue
    MarketProvider ..> InstrumentRepositoryContract : binds
    MarketProvider ..> PriceRepositoryContract : binds
    MarketProvider ..> PriceProviderPort : binds
```

### Tables et persistance

| Model | Table | Notes |
| --- | --- | --- |
| `Instrument` | `assets` | Aggregate root. Global scope `market` filtrant sur `InstrumentType::values()`. Défaut `type = Stock`. |
| `Price` | `asset_prices` | OHLCV, décimales `decimal(4)`, volume entier. FK `asset_id`. |
| `SectorAllocation` | `asset_sectors` | Poids `decimal(6)`. FK `asset_id`. |

### Casts Eloquent

| Model | Champ | Cast |
| --- | --- | --- |
| `Instrument` | `type` | `InstrumentType` |
| `Price` | `date` / `open,high,low,close` / `volume` | `Carbon` / `decimal:4` / `integer` |
| `SectorAllocation` | `sector` / `weight` | `Sector` / `decimal:6` |
