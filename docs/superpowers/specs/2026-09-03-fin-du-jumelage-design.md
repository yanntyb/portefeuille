# Fin du jumelage : PortfolioView et Wealth sans ports internes

## Pourquoi

Audit du 3 septembre 2026, trois passes (ports/adaptateurs/Datas, actions/traces/cache, front).
Le constat qui décide de tout :

| Mesure | Valeur |
| --- | --- |
| PortfolioView, plomberie / métier (Ports + Infrastructure + Datas / Actions + Services + Models) | 2 501 / 404 = 6,2×, zéro modèle |
| Wealth, même ratio | 1 290 / 553 = 2,3× |
| Tous contextes | ~8 100 lignes de plomberie pour ~6 850 de métier |
| Méthodes d'adaptateur qui recopient une Data dans sa jumelle sans rien changer | ~31 |
| Paires de Datas strictement identiques (nom, types, ordre des clés) | 8, plus un triplé (`AccountLineData` ≡ `AccountRowData` ≡ `WealthAccountData`) |
| Tests qui vérifient ces recopies | ~155 `it()` |
| Fichiers à ouvrir pour savoir d'où vient la valeur d'une enveloppe sur `/enveloppes/{id}` | 19, dont 3 providers |
| Fichiers pour le grand chiffre du tableau de bord | ~31, six contextes traversés |
| Déplacements du hash de `SnapshotInvariantTest` | 19, un par déplacement de forme entre contextes |

La cause est une doctrine : « PortfolioView ne lit ses voisins que par ses ports, et jumelle
leurs Datas à l'octet » (`.ai/rules/portfolio-view.md`). C'est la règle hexagonale appliquée
entre contextes d'un même monolithe, sans frontière technique derrière. Chaque port interne coûte
une interface, un adaptateur, une Data jumelle, un binding, un test de recopie, et un déplacement
du hash à chaque évolution.

Ce chantier retire cette couche là où elle ne protège rien. Il ne touche pas aux ports qui
tiennent une vraie frontière : Yahoo, cache, Python, dépôts Eloquent de Market, registres
d'extension (`AssetClassPort`, `IncomeSourcePort`).

## Décisions prises

1. **Les frontières entre contextes internes sont négociables.** Un port reste une frontière
   technique. Entre deux contextes du monolithe, on appelle l'action du voisin et on rend sa Data.
2. **Les tests d'un remap supprimé partent avec lui.** La couverture de comportement reste portée
   par les 256 `it()` d'actions, les tests de contrôleurs et `SnapshotInvariantTest`. Un test qui
   pince une logique survivante (filtre, tri, cas limite) suit cette logique dans sa nouvelle
   classe.
3. **Back d'abord, front ensuite.** Les types TS sont écrits à la main en miroir des Datas ; on
   stabilise les Datas avant d'y toucher.
4. **Pas de générateur de types TS** dans ce chantier.

## Ce qui n'en fait pas partie

- Les ports de Valuation et Income vers Portfolio (`TransactionHistoryPort`,
  `PositionHistoryPort`, `DividendHistoryPort`) : mêmes frontières internes, mais adaptateurs à
  logique propre, décorateurs de mémoïsation, cinq consommateurs chacun. Second chantier, même
  doctrine.
- La duplication fonctionnelle dans les contextes propriétaires : trois actions de série dans
  Valuation, `GetPortfolioAnalysis::positionsOf()` face à `GetPortfolioPositions`, deux
  `RollingWindow` homonymes, `LaravelSeriesCache` et `LaravelRealEstateCache` structurellement
  identiques, quatre sites de sommation des valeurs de marché.
- Une famille unique de sections front (`Summary`, `Evolution`, `Positions`, `Breakdown`,
  `Journal`, `Income`) : après ce chantier, une fois les Datas stables.
- `Market/Contracts` face à `Market/Ports` : à unifier ou à nommer, plus tard.
- L'entrée des enveloppes dans le blob hors-ligne.
- Le découpage de `lib/chart.ts` (985 lignes) et `lib/realEstate.ts` (482 lignes).

## Architecture back

### Doctrine

Un `Ports/` ne contient que des frontières techniques ou des points d'extension par registre.
Un contexte de composition (PortfolioView, Wealth) injecte les actions de ses voisins, appelle
leurs `Services/` purs quand il compose, et rend leurs Datas au front. Il ne possède que les
Datas qu'aucun voisin n'a : formes composites ou propres à une page.

Dépendances autorisées : PortfolioView → Portfolio (Actions, Datas), Valuation (Actions,
Services, Datas), Income (Actions, Datas, Enums), Market (Contracts, Models). Wealth → les mêmes
plus RealEstate. Personne ne lit PortfolioView ni Wealth. Pas de cycle : Valuation lit Portfolio,
PortfolioView lit les deux.

### PortfolioView après

| Dossier | Contenu |
| --- | --- |
| `Http/` | `AssetClassController`, `AssetClassCatalogController`, `AssetController`, `WalletController`. Chacun : auth, composeur, 404, `render`. |
| `Pages/` (nouveau) | `AssetClassPage`, `AssetPage`, `WalletPage`. Un composeur par page, servi au contrôleur et au snapshot. |
| `Actions/` | `GetClassCatalog`, `GetHoldingTrends`, `GetInstrumentDetail`, `BuildPortfolioViewSnapshot`, plus `BasketAnalysis` et `InstrumentAnalysis` déplacés depuis `Infrastructure/` : ce sont des calculs composés, pas des adaptateurs. |
| `Services/` | `SparklineReducer`, `CorrelationWindow`, `PriceHistoryWindow`, plus `ClassBreakdown` (ex `PortfolioTotals::classBreakdownFor`, pur : `HoldingLineData[]` → `ClassSliceData[]`) et `ChartStep` (ex `ValuationHistory::stepFor`, pur : labels → `ValuationGranularity`). |
| `Datas/` | 11 formes propres : `InstrumentDetailData`, `InstrumentMetaData`, `InstrumentSummaryData`, `CatalogLineData`, `HoldingTrendData`, `BasketAnalysisData`, `AnalysisInstrumentData`, `InstrumentAnalysisData`, `ClassSliceData`, `PriceHistoryData`, `SectorWeightData`. |

Supprimés : `Ports/` (10 interfaces), `Infrastructure/` (10 adaptateurs), 20 Datas jumelles
(`AccountRowData`, `AnalysisData`, `AssetLineData`, `AssetValuationData`,
`ClassTransactionLineData`, `ConcentrationData`, `ContributionLineData`, `DividendHistoryData`,
`DividendLineData`, `DrawdownData`, `EvolutionData`, `HoldingRowData`, `HoldingSnapshotData`,
`IncomeOverviewData`, `IncomeYearData`, `PerformanceLineData`, `PortfolioSummaryData`,
`PositionData`, `SectorSliceData`, `TransactionLineData`), `PortfolioViewProvider` et ses 10
bindings.

Ce que chaque adaptateur devient :

| Adaptateur | Remplacé par |
| --- | --- |
| `PortfolioTotals` | `GetPortfolioOverview`, `GetPortfolioPositions`, `GetPortfolioAnalysis` appelés directement ; `classBreakdownFor` → `Services\ClassBreakdown`. |
| `ValuationHistory` | `BuildPortfolioPerformances`, `BuildEvolutionSeries`, `BuildAssetPerformances`, `BuildAssetValuationSeries` appelés directement ; `stepFor` → `Services\ChartStep`. `drawdownFor` n'a aucun consommateur : il disparaît, `BasketAnalysis` compose déjà `BuildExposureSeries` et `Drawdown` lui-même. |
| `IncomeTotals` | `GetIncomeSummary`, `GetAssetDividendHistory` directement ; `supportsExposure` = `IncomeSource::forAssetClass() !== null`. `annualFor` n'a aucun consommateur : il disparaît. |
| `PortfolioHoldings` | `GetPortfolioPositions` directement. |
| `PortfolioSectors` | `GetSectorBreakdown` directement, rend `AllocationSliceData`. |
| `PortfolioAccounts` | `GetAccountBreakdown($user, HoldingScope::ofWallet($id))`. |
| `PortfolioTransactions` | `Portfolio\Actions\GetTransactionJournal`. |
| `MarketData` | `InstrumentRepositoryContract`, `PriceRepositoryContract`, `SectorRepositoryContract` et les modèles Market, injectés dans les actions qui en ont besoin (`GetInstrumentDetail`, `GetClassCatalog`, `GetHoldingTrends`, `AssetPage`). Les Datas `InstrumentSummaryData`, `InstrumentMetaData`, `PriceHistoryData`, `SectorWeightData` restent à PortfolioView : Market n'a pas d'équivalent. |
| `BasketAnalysis`, `InstrumentAnalysis` | Déplacés dans `Actions/`, même corps, dépendances directes au lieu des ports. |

### Wealth après

`Ports/` ne garde que `AssetClassPort` : trois implémentations (`PortfolioAssetClass` paramétrée,
`RealEstateClass`, `CashClass`), un registre, un vrai point d'extension.

Supprimés : `AccountsPort`, `TransactionsPort`, `CashPort` ; `PortfolioAccounts`,
`PortfolioLedger`, `PortfolioCash` ; `GetWealthAccounts`, `GetWealthTransactions` (une ligne
chacune) ; `WealthAccountData`, `WealthTransactionLineData`.

`CashClass` absorbe le corps de `PortfolioCash` : elle injecte `GetPortfolioOverview`,
`GetCashMovements`, `CashLedger`, `BuildEvolutionSeries`, `SeriesAligner`, `InvestedCapital`,
`PortfolioInvestedCapital`, et porte `snapshotFor()` et `seriesFor()` elle-même.

`WealthProvider::registers()` perd ses paramètres `$transactions` et `$accounts`, garde `$extra`
et le registre, et reçoit le `scoped` de `PortfolioInvestedCapital`.

`Pages/DashboardPage` composeur ajouté, `Http/DashboardController` réduit.

### Portfolio gagne ce que trois contextes recopiaient

- **`Actions/GetTransactionJournal`** : `__invoke(int $userId, ?HoldingScope $scope = null)` et
  `forAsset(int $userId, int $assetId)`. Rend `Datas\TransactionLineData` (13 champs, l'actuelle
  `ClassTransactionLineData`). Remplace `Wealth\PortfolioLedger` et les deux méthodes de
  `PortfolioView\PortfolioTransactions`. L'asymétrie du cash est conservée telle quelle : un
  périmètre par classe exclut les lignes sans `asset_id`, un périmètre par enveloppe les inclut.
  Le montant passe par `TransactionFlow`, seul site.
- **`GetAccountBreakdown`** prend `?HoldingScope $scope = null` en dernier paramètre, comme les
  autres lectures. Seul le filtre par enveloppe a un sens ici ; un périmètre par classe est ignoré.
- **`PositionLineData`** devient `JsonSerializable` : elle sort désormais dans
  `InstrumentDetailData::$position`.
- **`PortfolioProvider::registers($app)`** reçoit les quatre `scoped` que porte aujourd'hui
  `AppServiceProvider` : `GetPortfolioOverview`, `GetPortfolioPositions`, `GetRealizedGains`,
  `GetCashMovements`. La mémoïsation par requête se lit désormais à côté du contexte qui en
  dépend.

### Market

`PriceProviderPort` n'a aucun consommateur. Il part avec `DatabaseAssetPriceAdapter` (qui
n'implémente rien d'autre), son binding dans `MarketProvider::registers()` et l'`implements`
correspondant dans `YahooFinanceAdapter`. Les méthodes de Yahoo qu'il déclarait ne sont retirées
que si aucun autre port ne les exige.

### Providers

`AppServiceProvider::register()` ne contient plus que des appels `XxxProvider::registers()`.
Plus aucun `bind` ni `scoped` direct. `PortfolioViewProvider` est supprimé.

### JSON : trois clés en plus, rien retiré

Vérifié à la lecture : `AccountLineData::jsonSerialize()` produit déjà `accountType` (valeur) et
`accountTypeLabel`, à l'octet ce que rendaient `AccountRowData` et `WealthAccountData`.
`AllocationSliceData` a les quatre champs de `SectorSliceData`. Les Datas de Valuation et Income
sont identiques à leurs jumelles.

| Page | Prop | Clé ajoutée | Cause |
| --- | --- | --- | --- |
| exposition | `overview` | `netContributions` | `PortfolioOverviewData` remplace `PortfolioSummaryData` |
| fiche instrument | `instrument.position` | `assetId` | `PositionLineData` remplace `PositionData` |
| fiche instrument | `instrument.transactions` | `assetId`, `assetName` | un seul `TransactionLineData` |

Le hash de `SnapshotInvariantTest` bouge une fois. Le front ne casse pas : une clé en plus est
ignorée. Les types TS sont rattrapés en phase front.

### Trace après

Valeur totale d'une enveloppe : `WalletController` → `WalletPage` → `GetAccountBreakdown` →
`GetPortfolioOverview` → `HoldingValuator` → `Holding` et `Price`. Six fichiers au lieu de dix-neuf,
aucun provider sur le chemin.

## Composeurs de page

### `App\Shared\Inertia\PageProps`

Une page a deux tas de props : ce qui part avec le document, ce qui arrive après. Le contrôleur
enveloppe le second tas dans `Inertia::defer()` ; le snapshot l'appelle. Aujourd'hui,
`BuildPortfolioViewSnapshot::listFor()` et `page()` recopient à la main le corps de
`AssetClassController` et `AssetController`. `PageProps` remplace la recopie par une seule
définition.

```php
namespace App\Shared\Inertia;

final readonly class PageProps
{
    /**
     * @param array<string, mixed> $sync
     * @param array<string, DeferredProp> $deferred
     */
    public function __construct(public array $sync, public array $deferred) {}

    /** Sync tel quel ; chaque différée devient Inertia::defer($closure, $group). */
    public function render(string $component): Response;

    /** Sync plus chaque closure appelée : le JSON que la page rendrait toutes sections dépliées. */
    public function resolve(): array;
}

final readonly class DeferredProp
{
    public function __construct(public Closure $resolve, public string $group) {}
}
```

Une quarantaine de lignes, testées seules : `render()` produit un `DeferredProp` Inertia par
entrée différée avec le bon groupe, `resolve()` appelle chaque closure une fois.

### Composeurs

| Composeur | Signature | Sync | Différé (groupe) |
| --- | --- | --- | --- |
| `PortfolioView\Pages\AssetClassPage` | `for(int $userId, AssetClass $exposure): PageProps` | `assetClass`, `overview` | `trends` (tendances), `evolutionSeries` (evolution), `performances`, `basketAnalysis` (analyse), `transactions`, `sectorBreakdown` (secteurs, si `hasSectors()`) |
| `PortfolioView\Pages\AssetPage` | `for(int $userId, int $assetId): ?PageProps` | `instrument`, `performances`, `dividends` (si `IncomeSource::forAssetClass()` non null) | `priceHistory`, `valuation`, `analysis` |
| `PortfolioView\Pages\WalletPage` | `for(int $userId, int $walletId): ?PageProps` | `account` | `positions`, `breakdown` (repartition), `evolution`, `performances`, `basketAnalysis` (analyse), `sectorBreakdown` (secteurs), `transactions` |
| `Wealth\Pages\DashboardPage` | `for(int $userId): PageProps` | `overview` | `series` (evolution), `income` (revenus), `sectors` (secteurs), `transactions`, `accounts` (enveloppes) |

`null` signifie « la ressource n'existe pas ou n'appartient pas à ce porteur » ; le contrôleur
répond 404. Les noms de props, groupes et conditions sont ceux d'aujourd'hui : le front ne voit
aucune différence.

Le tableau de bord porte aussi `sync` (`MarketSyncStatePort::current()`). Ce n'est pas une donnée
de page mais un état global, déjà exclu du snapshot : le contrôleur l'ajoute lui-même après
`render()`, le composeur ne le connaît pas.

Le catalogue (`AssetClassCatalogController`) n'a que des props synchrones et n'entre pas dans le
snapshot : il garde son contrôleur tel quel, sans composeur.

### Contrôleurs

```php
public function __invoke(int $id): Response
{
    $page = $this->walletPage->for(auth()->id() ?? 0, $id) ?? abort(404);

    return $page->render('Wallet/Show');
}
```

### Snapshot

`BuildPortfolioViewSnapshot` boucle sur `AssetClass::cases()` et rend
`AssetClassPage::for()->resolve()` par exposition, puis sur `GetPortfolioPositions` et rend
`AssetPage::for()->resolve()` par actif détenu. `BuildWealthSnapshot` rend
`DashboardPage::for()->resolve()`. Le blob et la page sont structurellement identiques, par
construction.

## Front

Quatre chantiers mécaniques, aucun changement de rendu.

**`components/DeferredBlock.vue`.** Enveloppe `<Deferred :data>` ; `#fallback` rend un squelette
de `lines` lignes (défaut 3), surchargeable par un slot `#fallback` pour `ChartSkeleton` ;
`#rescue` porte « Données indisponibles hors-ligne. » une seule fois. Remplace les 18 copies. Un
test : squelette, rescue, contenu.

**Un journal, un type.** `TransactionLine` gagne `assetId: number | null` et
`assetName: string | null`. `NamedTransactionLine` et `WealthTransactionLine` disparaissent. Les
trois `TransactionsSection` (`instrument/`, `instruments/`, `dashboard/WealthTransactionsSection`)
fusionnent en `components/transactions/TransactionsSection.vue`, props `lines`, `variant`,
`emptyLabel`, `collapsible`. Leurs tests fusionnent.

**Un résumé.** `instruments/ValuationSection` et `dashboard/WealthSummarySection` deviennent
`components/PortfolioSummarySection.vue`, props : les chiffres affichés, pas l'objet entier. Le
`const eur = (v) => formatEur(v, 0)` redéfini dans trois composants disparaît au profit de l'appel
direct.

**Une ventilation.** Les quatre `map` vers `SectorBreakdownRow` (`instrument/SectorsSection`,
`instruments/SectorsBlock`, `dashboard/WealthSectorsSection`, `Wallet/Show` inline) deviennent un
`SectorsSection.vue` qui reçoit des `SectorBreakdownRow[]`, et des convertisseurs nommés dans
`lib/sector.ts` (`rowsFromWeights`, `rowsFromSlices`), testés là.

**Dialecte unique du chargement.** `null` = pas encore arrivé, vide = rien à montrer. La prop
`loaded` disparaît où `null` suffit. `rescuedProps` reste le seul signal hors-ligne, consommé par
`DeferredBlock`. `Wallet/Show` est déjà sur `null` ; son commentaire de dix lignes tombe.

**Types TS rattrapés.** `PortfolioOverview` gagne `netContributions: number`, le type de
`instrument.position` gagne `assetId: number`.

## Tests

**Supprimés avec leur sujet.** Les tests co-localisés des adaptateurs de recopie retirés :
PortfolioView (`PortfolioTotalsTest` hors cas `classBreakdownFor`, `ValuationHistoryTest` hors cas
`stepFor`, `IncomeTotalsTest`, `MarketDataTest`, `PortfolioSectorsTest`, `PortfolioAccountsTest`),
Wealth (`PortfolioAccountsTest`), `GetWealthAccountsTest`, `tests/Unit/PortfolioView/DatasTest`,
`tests/Unit/PortfolioView/ProviderBindingTest`, `DatabaseAssetPriceAdapterTest`.

**Qui suivent leur logique.**

| Test actuel | Devient |
| --- | --- |
| `PortfolioTransactionsTest`, `PortfolioLedgerTest`, `GetWealthTransactionsTest` | `Portfolio/Actions/GetTransactionJournalTest`, un seul fichier : l'asymétrie du cash y est pincée avec un achat dans l'enveloppe voisine, pas un versement ; le tri date puis id ; le montant par `TransactionFlow` |
| `CashClassTest` | reste, et couvre ce que `PortfolioCash` portait (il n'avait pas de test propre) |
| `PortfolioTotalsTest`, cas `classBreakdownFor` | `PortfolioView/Services/ClassBreakdownTest`, pur, `new`, sans base |
| `ValuationHistoryTest`, cas `stepFor` | `PortfolioView/Services/ChartStepTest` |
| `BasketAnalysisTest`, `InstrumentAnalysisTest` | déplacés dans `Actions/` avec leur classe |
| `GetAccountBreakdownTest` | gagne un cas « périmètre par enveloppe » |

**Nouveaux.** `App/Shared/Inertia/PagePropsTest` ; un test par composeur : clés sync et différées
attendues, groupes, `null` sur ressource étrangère ou inconnue, condition `hasSectors()` et gate
dividendes.

**Inchangés.** Les tests de contrôleurs, sauf les cas « par leurs seuls ports » (trois dans
`AssetClassControllerTest`, un dans `AssetControllerTest`) qui prouvaient l'isolation par port et
partent avec lui.
Les tests des actions de Portfolio, Valuation, Income.

**`SnapshotInvariantTest`.** Hash mis à jour une fois. Entrée d'en-tête : « Modifié une vingtième
fois : fin du jumelage, trois clés ajoutées (`netContributions`, `position.assetId`,
`transactions[].assetId/assetName`). »

Front : `DeferredBlock.test.ts`, le test fusionné de `TransactionsSection`,
`PortfolioSummarySection.test.ts`, `SectorsSection.test.ts`, les convertisseurs dans
`lib/sector.test.ts`. Les tests des composants fusionnés partent avec eux. `vue-tsc --noEmit` et
`vitest run` verts.

## Règles `.ai/rules`

- `contexts.md` : nouvelle section « Un port est une frontière technique ». Entre deux contextes
  du monolithe, on appelle l'action du voisin et on rend sa Data ; `Ports/` ne contient que HTTP,
  cache, Python, dépôts, registres d'extension. Le piège `HoldingRowData` de la section AssetClass
  disparaît.
- `portfolio-view.md` : les sections « ne lit ses voisins que par ses ports », « jumelle leurs
  Datas » et « ne calcule rien » tombent. Une section les remplace : PortfolioView compose sans
  port ; un composeur par page sert contrôleur et snapshot ; ses Datas propres sont celles
  qu'aucun voisin n'a. La section sur `HoldingScope` reste.
- `wealth.md` §1 : la liste des actions retirées devient « pas de port vers Portfolio ».
- `infrastructure.md` : `PortfolioHoldings` disparaît de la phrase, l'interdit `Holding::query()`
  hors Portfolio reste et couvre PortfolioView et Income.
- `tests.md` §2 : « trois Datas jumelles d'opération » devient « `TransactionLineData` expose
  `id` ».

Enregistrées par `record-rule` pour les ajouts, éditées à la main pour les retraits.

## Ordre de construction

Chaque étape laisse la suite verte et se committe seule.

1. `App\Shared\Inertia\PageProps` et `DeferredProp`, avec test.
2. `Portfolio\Actions\GetTransactionJournal` et `TransactionLineData`, avec test ; les trois
   copies basculent dessus (les adaptateurs appellent l'action en attendant leur suppression).
3. `GetAccountBreakdown` prend un `HoldingScope` ; `PositionLineData` devient sérialisable.
4. Composeurs et contrôleurs de PortfolioView, un par un : `WalletPage`, `AssetClassPage`,
   `AssetPage`. Chaque composeur appelle directement les actions voisines ; le contrôleur
   correspondant bascule ; les ports qu'il n'utilise plus perdent un consommateur.
5. `BuildPortfolioViewSnapshot` sur les composeurs. Hash mis à jour.
6. Suppression de `Ports/`, `Infrastructure/`, des 20 Datas, du provider de PortfolioView ;
   `BasketAnalysis` et `InstrumentAnalysis` déplacés dans `Actions/` ; `ClassBreakdown` et
   `ChartStep` extraits ; tests déplacés ou supprimés.
7. Wealth : `DashboardPage`, `CashClass` absorbe `PortfolioCash`, suppression des trois ports et
   de leurs adaptateurs, actions et Datas ; `BuildWealthSnapshot` sur le composeur.
8. Providers : `scoped` de Portfolio dans `PortfolioProvider`, `PortfolioInvestedCapital` dans
   `WealthProvider`, `PriceProviderPort` et `DatabaseAssetPriceAdapter` retirés.
9. Règles `.ai/rules`.
10. Front : `DeferredBlock`, journal, résumé, ventilation, dialecte, types.
