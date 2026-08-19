# Dividendes encaissés — source Marché et contexte Income

Date : 2026-08-19
Statut : design validé (brainstorming). Sous-projet 1 sur 2 : le revenu **déjà perçu**. Le
prévisionnel (calendrier des prochains détachements, revenu annuel projeté) fera l'objet de sa
propre spec une fois celui-ci en place.

## Context

L'application ignore totalement les dividendes : aucune colonne, aucun modèle, aucun script
Python, aucun cas de `TransactionType`. Le seul endroit qui prononce le mot est
`SyncPricesCommand`, dans l'aide de son option `--since`, pour prévenir qu'un détachement force
un rattrapage complet de l'historique des cours.

Un ETF distribuant ou une action à dividende produit donc aujourd'hui un revenu invisible : il
n'apparaît nulle part, ni sur la fiche instrument, ni au tableau de bord.

Ce qui existe déjà et sert de socle :

- **Le write-side Marché est un chemin balisé.** `SyncAssetPrices` + `PriceFeedPort` +
  `fetch_prices_bulk.py` + `market:sync-prices` forment un patron complet (spec
  `2026-08-13-market-sync-prices-design.md`) que la synchronisation des dividendes recopie sans
  rien inventer.
- **Les quantités historiques sont déjà reconstituées.** `ValuationCalculator::calculateDaily()`
  rejoue les transactions pour obtenir la quantité détenue jour par jour. La quantité détenue à
  une date de détachement n'exige donc aucune mécanique nouvelle, seulement le même rejeu.
- **Le patron « contexte de calcul » est établi.** `Valuation` déclare ses propres ports vers
  Market et Portfolio, ne dépend d'aucun autre contexte en direct, et se câble par un
  `Provider::registers()` appelé depuis `AppServiceProvider`. `Income` suit ce moule.

## Décisions (validées)

- **Montant calculé, pas saisi.** Le dividende par action vient de Yahoo comme les cours. Le
  montant perçu est déduit : `montant par action × quantité détenue à l'ex-date`. Aucune saisie
  manuelle, aucun écran de validation.
- **Revenu à part, gain latent inchangé.** Le dividende n'entre ni dans `avg_cost`, ni dans le
  gain (`valeur − coût`), ni dans `holdings_projection`. Il s'accumule dans un total « revenus
  perçus » affiché à côté. Aucun chiffre déjà visible ne change de valeur.
- **Pas de nouveau `TransactionType`.** Un dividende n'est pas une transaction : `quantity` et
  `unit_price` n'ont pas de sens pour lui, et l'ajouter à `transactions` obligerait
  `TransactionObserver`, `ValuationCalculator` et `GetPortfolioOverview` à filtrer un type
  partout. `Portfolio` n'est pas modifié par ce sous-projet.
- **« Distribuant » n'est pas un champ.** Un instrument est distribuant s'il a au moins une ligne
  `asset_dividends`. Un ETF capitalisant garde la table vide et n'affiche aucune section. Rien à
  cocher, rien à maintenir.
- **Le contexte `Income` est multi-sources dès le départ.** Son noyau agrège des
  `IncomeReceiptData` sans savoir d'où ils viennent ; le dividende est une source parmi d'autres,
  derrière `IncomeSourcePort`. L'objectif explicite est d'accueillir plus tard les revenus d'un
  investissement locatif sans casser de frontière. Aucun bien locatif n'existe encore dans
  l'application : `IncomeReceiptData::$assetId` est nullable et accompagné d'un `label`, ce qui
  suffit à ne rien fermer.
- **Détention à l'ex-date : transactions strictement antérieures.** Un achat passé le jour du
  détachement ne donne pas droit au dividende ; la comparaison est `date < ex_date`. Une quantité
  nulle ou négative ne produit aucun reçu.
- **Rendement sur coût** = perçu sur 12 mois glissants ÷ (`avg_cost × quantité` de la position
  courante). Il reste sur la fiche instrument, hors du noyau : un loyer se rapporte au prix
  d'achat d'un bien, pas à un prix de revient unitaire.
- **Périmètre du feed : actions et ETF.** `supportsDividendFeed()` ne couvre que
  `InstrumentType::Stock` et `InstrumentType::ETF`. Crypto, matière première : pas de dividende.
  Obligation : Yahoo ne la couvre pas, comme pour les cours.

Hors périmètre de SP-1 : le calendrier des détachements à venir et le revenu annuel projeté
(SP-2), toute saisie manuelle d'un dividende, la retenue à la source, la conversion de devise, le
dividende réinvesti automatiquement (DRIP), et les revenus locatifs eux-mêmes.

### Limite connue : montants bruts, sans devise ni fiscalité

Le schéma n'a aucune notion de devise et Yahoo renvoie le dividende dans celle de la cotation.
Les montants sont donc **pris tels quels et supposés en euros**, et ils sont **bruts** : ni
retenue à la source étrangère, ni prélèvements sociaux, ni frais de courtier. Un titre coté hors
zone euro affichera un montant faux, et tout titre affichera plus que ce que le relevé du
courtier annonce. C'est le prix du « zéro saisie » retenu ci-dessus.

### Limite connue : les clôtures stockées sont déjà ajustées du dividende

`fetch_prices_bulk.py` demande des séries ajustées (`auto_adjust=True`). Chez Yahoo, un
détachement réécrit rétroactivement l'historique à la baisse : la performance lue par les séries
`Valuation` incorpore donc déjà, partiellement, le rendement des dividendes passés.

C'est une raison de fond, et pas seulement de lisibilité, pour ne **pas** additionner les
« revenus perçus » au gain latent : la somme compterait deux fois une partie du même rendement.
Le total reste affiché à côté, jamais dedans.

## Architecture

### Flux

```
market:sync-dividends
  └─ SyncAssetDividends
       ├─ InstrumentRepositoryContract::findAll()   (ticker != null, supportsDividendFeed)
       ├─ DividendFeedPort::fetchDividends([DividendRequestData])   → YahooFinanceAdapter
       │                                                              └─ fetch_dividends_bulk.py
       └─ DividendRepositoryContract::upsertForAsset()              → asset_dividends

Affichage
  DashboardController ──▶ Income\Actions\GetIncomeSummary
                    └──▶ Income\Actions\GetAnnualIncome
                              └─ IncomeSourceRegistry
                                   └─ DividendIncomeSource (IncomeSourcePort)
                                        ├─ DividendHistoryPort   → Market (asset_dividends)
                                        ├─ PositionHistoryPort   → Portfolio (transactions)
                                        └─ DividendCalculator

  InstrumentDetailController ──▶ Income\Sources\Dividend\Actions\GetAssetDividendHistory
                                        └─ mêmes ports + DividendCalculator
```

### Table — `database/migrations/2026_08_19_000007_create_asset_dividends_table.php`

```php
Schema::create('asset_dividends', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
    $table->date('ex_date');
    $table->decimal('amount_per_share', 12, 6);
    $table->timestamps();

    $table->unique(['asset_id', 'ex_date']);
    $table->index('ex_date');
});
```

Six décimales et non quatre comme les cours : un dividende trimestriel d'ETF se compte en
centièmes de centime, et l'arrondi à 4 décimales dérive dès qu'on le multiplie par une position.
`cascadeOnDelete` comme `asset_prices` : un dividende sans instrument n'a aucun sens, contrairement
à une transaction dont l'historique se garde même orpheline.

### Modèle — `app/Contexts/Market/Models/Dividend.php`

Calqué sur `Price` : `$table = 'asset_dividends'`, casts `ex_date` en `date` et
`amount_per_share` en `decimal:6`, relation `instrument()`, `#[UseFactory(DividendFactory::class)]`.
`DividendFactory` dans `app/Contexts/Market/Factories/`.

### Port de fetch — `app/Contexts/Market/Ports/DividendFeedPort.php`

```php
interface DividendFeedPort
{
    public function supportsDividendFeed(InstrumentType $type): bool;

    /**
     * @param  array<int, DividendRequestData>  $requests
     * @return array<string, array<int, DividendData>> dividendes par ticker
     *
     * @throws DividendFeedException quand la récupération échoue en totalité
     */
    public function fetchDividends(array $requests): array;
}
```

Un ticker sans détachement sur sa fenêtre est **absent** du résultat, comme pour les cours : le
feed ne sait pas distinguer un capitalisant d'un ticker mort. `DividendFeedException` reprend la
forme de `PriceFeedException` (`fetchFailed()`, propriété `reason`).

`DividendRequestData` : `ticker`, `startDate`, `endDate`. `DividendData` : `exDate`,
`amountPerShare`, plus `fromArray()`.

### Write-side — `app/Contexts/Market/Contracts/DividendRepositoryContract.php`

```php
public function latestForAsset(int $assetId): ?Dividend;

/** @return list<array{assetId: int, exDate: string, amountPerShare: float}> */
public function forAssets(array $assetIds): array;

/**
 * Insère ou met à jour les dividendes d'un actif. Appariement sur (asset_id, ex_date),
 * valeurs écrasées : le fournisseur révise ses montants.
 *
 * @param  array<int, DividendData>  $dividends
 */
public function upsertForAsset(int $assetId, array $dividends): int;
```

`forAssets()` rend des tableaux et non des modèles, pour la même raison que
`closesForAssetsSince()` : le calcul lit trois colonnes et n'a que faire d'une hydratation.

### Adaptateur — `YahooFinanceAdapter`

Implémente `DividendFeedPort` en plus de ses quatre ports actuels. `YahooScript` gagne
`case DividendsBulk = 'fetch_dividends_bulk.py'`. La fenêtre passe par le `window()` existant, qui
corrige déjà le `end` exclusif de yfinance. Le timeout est celui du lot
(`BULK_TIMEOUT_SECONDS`), pour la même raison : un seul process Python pour tout le catalogue.

`fetch_dividends_bulk.py` lit `yf.Ticker(t).dividends` — une `Series` indexée par date — la filtre
sur la fenêtre, et sérialise avec `allow_nan=False`. La règle `.ai/rules/python.md` s'applique
telle quelle : aucune valeur non finie ne doit atteindre `json.dumps`, sous peine de perdre tout
le lot pour une ligne.

Pas de branche « lot » séparée ici : `Ticker.dividends` n'a pas d'équivalent groupé chez yfinance,
donc le script boucle sur les tickers dans un seul process. Le gain reste celui visé — un boot
Python et un import yfinance pour tout le catalogue au lieu de N.

### Action — `app/Contexts/Market/Actions/SyncAssetDividends.php`

Copie structurelle de `SyncAssetPrices` :

- instruments à synchroniser : `findAll()` (ou un seul via `$assetId`), filtrés sur
  `ticker !== null` et `supportsDividendFeed($type)` ;
- `startDateFor()` : `--since` s'il est fourni, sinon le dernier `ex_date` connu, sinon un repli
  de **60 mois** — et non 12 comme les cours. L'historique perçu doit couvrir la vie des
  positions, pas la dernière année ;
- échec global du feed : log + `DividendSyncReportData(failed: ..., error: ...)`, jamais
  d'exception qui remonte ;
- retour : `DividendSyncReportData` (`synced`, `failed`, `error`, `total()`, `syncedCount()`,
  `isTotalFailure()`), même forme que `PriceSyncReportData`.

### Commande — `app/Contexts/Market/Console/SyncDividendsCommand.php`

`market:sync-dividends {--asset=} {--since=}`, calquée sur `SyncPricesCommand` : validation du
format `Y-m-d`, validation de l'existence de l'instrument, rapport ligne par ligne, résumé
pluralisé, `FAILURE` uniquement si tout échoue.

Ajoutée à `withCommands()` et planifiée dans `bootstrap/app.php` :

```php
$schedule->command('market:sync-dividends')
    ->weeklyOn(6, '23:45')
    ->appendOutputTo(storage_path('logs/market-sync-dividends.log'));
```

Hebdomadaire et non quotidien : un détachement est un évènement rare, et le rattrapage part
toujours du dernier `ex_date` connu. L'horaire suit celui des cours pour ne pas croiser deux
process Python.

### Noyau du contexte `Income`

`app/Contexts/Income/`. Le noyau ne connaît aucune source par son nom.

```
Income/
├── Enums/IncomeSource.php              # case Dividend = 'dividend'
├── Datas/IncomeReceiptData.php         # source, date, amount, assetId (nullable), label
├── Datas/IncomeSummaryData.php         # totalReceived, last12Months, bySource + empty()
├── Datas/AnnualIncomeData.php          # year, total, bySource
├── Ports/IncomeSourcePort.php
├── Infrastructure/IncomeSourceRegistry.php
├── Actions/GetIncomeSummary.php
├── Actions/GetAnnualIncome.php
└── IncomeProvider.php
```

```php
interface IncomeSourcePort
{
    public function source(): IncomeSource;

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array;
}
```

Le port ne dit pas si les reçus sont calculés ou lus en base : le dividende les calcule, un loyer
les lira. Les deux modèles passent sans toucher au noyau.

`IncomeSourceRegistry` reçoit la liste des sources (résolue par `IncomeProvider` depuis un tag du
conteneur) et l'expose en itérable. `GetIncomeSummary` et `GetAnnualIncome` la parcourent et ne
nomment jamais `Dividend` : `bySource` est indexé par la valeur de l'enum, donc l'ajout d'une
source ne change aucune signature.

`AnnualIncomeData::$bySource` est produit dès maintenant, alors qu'une seule source existe : la
ventilation ne coûte rien à écrire et évite de refaire le graphe annuel quand le locatif arrive.

Pas de cache : quelques centaines de reçus par utilisateur. `SeriesCachePort` reste disponible si
une mesure le réclame.

### Source dividende — `app/Contexts/Income/Sources/Dividend/`

```
Sources/Dividend/
├── DividendIncomeSource.php                # implements IncomeSourcePort
├── Services/DividendCalculator.php         # cœur sans DB
├── Datas/DividendReceiptData.php           # exDate, quantity, amountPerShare, amount
├── Datas/DividendRecordData.php            # assetId, exDate, amountPerShare — entrée du calcul
├── Datas/PositionRecordData.php            # assetId, date, isSell, quantity — entrée du calcul
├── Datas/AssetDividendHistoryData.php      # receipts, totalReceived, last12Months, yieldOnCost
├── Actions/GetAssetDividendHistory.php
├── Ports/DividendHistoryPort.php           # → Market
├── Ports/PositionHistoryPort.php           # → Portfolio
├── Infrastructure/MarketDividendHistory.php
└── Infrastructure/PortfolioPositionHistory.php
```

`DividendCalculator` est le seul endroit où le calcul vit, et il ne touche ni à la base ni au
conteneur :

```php
/**
 * @param  list<PositionRecordData>  $transactions  triées par date croissante
 * @param  list<DividendRecordData>  $dividends
 * @return list<DividendReceiptData>
 */
public function receipts(array $transactions, array $dividends): array
```

Règle unique et testable : pour chaque dividende, la quantité retenue est la somme des deltas des
transactions dont `date < ex_date` (achat positif, vente négative), toutes enveloppes confondues.
Quantité ≤ 0 → aucun reçu. `amount = quantity × amountPerShare`.

`DividendIncomeSource::receiptsFor()` convertit ces reçus en `IncomeReceiptData` génériques
(`source: Dividend`, `date: exDate`, `label: nom de l'instrument`) pour l'agrégation.

`GetAssetDividendHistory(int $userId, int $assetId)` reste dans ce sous-dossier : « par
instrument » n'a pas de sens pour un loyer. Il calcule aussi le rendement sur coût, à partir de la
position courante lue via `PositionHistoryPort`.

`DividendReceiptData` n'est jamais vu par le noyau : seul l'affichage de la fiche instrument le
consomme.

### Câblage — `AppServiceProvider`

```php
MarketProvider::registers(
    // ... arguments actuels
    dividendRepository: EloquentDividendRepository::class,
    dividendFeed: YahooFinanceAdapter::class,
);

IncomeProvider::registers(
    app: $this->app,
    sources: [DividendIncomeSource::class],
);
```

`IncomeProvider::registers()` reçoit la liste des classes de sources, les tague, et lie
`IncomeSourceRegistry` sur ce tag. Brancher le locatif plus tard, c'est ajouter une classe à ce
tableau.

### Page instrument — `InstrumentDetailController`

Nouvelle prop différée, à côté de `priceHistory` et `valuation` :

```php
'dividends' => Inertia::defer(
    fn () => app(GetAssetDividendHistory::class)($userId, $id)
),
```

`resources/js/components/instrument/DividendsSection.vue`, montée depuis `Pages/Instruments/Show.vue` :

- ne se rend **pas du tout** si `receipts` est vide — un capitalisant ou une crypto n'affiche
  aucune section vide ;
- en-tête : total perçu, perçu sur 12 mois, rendement sur coût ;
- tableau : date de détachement, montant par action, quantité détenue, montant perçu ;
- `ChartSkeleton` pendant le chargement différé, comme les autres sections.

### Tableau de bord — `DashboardController`

Un seul groupe différé supplémentaire, `revenus` :

```php
'income' => Inertia::defer(fn () => $user !== null
    ? app(GetIncomeSummary::class)($user->id)
    : IncomeSummaryData::empty(), 'revenus'),
'annualIncome' => Inertia::defer(fn () => $user !== null
    ? app(GetAnnualIncome::class)($user->id)
    : [], 'revenus'),
```

`resources/js/components/dashboard/IncomeSection.vue` : les chiffres (total perçu, 12 mois
glissants, ventilation par source) et un histogramme du revenu par année via `BaseChart`.

Le titre de la section est **« Revenus »**, pas « Dividendes » : c'est elle qui accueillera le
locatif, et la renommer plus tard changerait un repère visuel déjà acquis. Tous les libellés sont
en français, comme le reste de l'interface.

## Tests

Les tests vivent à côté des classes dans `app/Contexts` (`pest()` les charge via
`'../app/Contexts'`), sauf navigateur et bout-en-bout dans `tests/`.

- **`DividendCalculatorTest`** — unit, sans base, le gros du filet : quantité à l'ex-date, achat le
  jour même du détachement exclu, vente avant détachement, position soldée puis rachetée, plusieurs
  enveloppes agrégées, aucun dividende, aucune transaction.
- **`SyncAssetDividendsTest`** — reprise au dernier `ex_date`, repli 60 mois, filtre de type
  (crypto et obligation exclues), ticker absent du résultat, échec global du feed rapporté sans
  exception.
- **`EloquentDividendRepositoryTest`** — upsert qui écrase sur `(asset_id, ex_date)`,
  `latestForAsset`, `forAssets` multi-actifs.
- **`SyncDividendsCommandTest`** — `--since` invalide, `--asset` inconnu, rapport, code de sortie
  d'échec total.
- **`YahooFinanceAdapterTest`** étendu — `fetchDividends()` via `FakePythonRunner`, plus
  `supportsDividendFeed()` par type.
- **`fetch_dividends_bulk.py`** — testé sans réseau depuis Pest : le stub
  `tests/Fixtures/python/yfinance.py` gagne un attribut `dividends` (`pd.Series` indexée par date,
  avec une valeur non finie pour éprouver `allow_nan=False`), injecté par `PYTHONPATH` comme
  aujourd'hui.
- **`GetIncomeSummaryTest`, `GetAnnualIncomeTest`** — feature avec factories, dont deux années
  distinctes et la ventilation `bySource`.
- **`GetAssetDividendHistoryTest`** — feature : rendement sur coût, 12 mois glissants, instrument
  sans dividende.
- **`IncomeSourceRegistryTest`** — l'agrégation ne dépend pas du nombre de sources : une fausse
  seconde source suffit à le prouver.
- **Navigateur** — fiche instrument avec et sans dividendes (section présente / absente), section
  Revenus du tableau de bord après résolution du groupe différé.
- **`portfolioFixture()`** gagne un paramètre `dividends` optionnel, plutôt qu'un fixture parallèle
  à maintenir.
