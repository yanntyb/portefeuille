# Commande de rafraîchissement des prix des titres

Date : 2026-08-13
Statut : design validé (brainstorming). Ouvre le write-side prix du contexte Market, laissé
« À faire » par `docs/refactor-status.md` (§2, ligne « Couche tâche planifiée »).

## Context

Le contexte Market lit des prix mais n'en écrit jamais. `PriceRepositoryContract` n'expose que
des méthodes de lecture (`latestForAsset`, `forAssetOnDate`, `forAssetSince`, `forAssets`,
`filterAssetIdsHavingPriceSince`) et aucun code applicatif n'insère dans `asset_prices`. Les
prix présents en base viennent de l'ancienne architecture `app/Domains/*`, supprimée : la
commande `SyncAssetPricesCommand` et son `PriceSyncService` ont disparu avec le retrait de
Filament (`9debd90`).

Conséquences visibles aujourd'hui :

- `bootstrap/app.php:24-28` porte un TODO et un `$schedule->command('securities:fetch-prices')`
  commenté : le sync quotidien est en pause depuis la refonte DDD.
- Tout ce qui dépend d'un prix récent — `GetPortfolioOverview` (`latestForAsset`), les séries
  Valuation, `InstrumentView` — travaille sur un historique qui se périme.
- `app/Contexts/Market/Infrastructure/Python/fetch_prices_bulk.py` existe sur disque, gère déjà
  le téléchargement groupé yfinance, et n'est référencé par aucun PHP (absent de l'enum
  `YahooScript`).

Point dur d'architecture : `PriceProviderPort` est bindé sur `DatabaseAssetPriceAdapter`
(lecture en base) dans `AppServiceProvider.php:38`. `YahooFinanceAdapter` implémente le même
port mais n'est pas bindé. Un sync a besoin d'un accès **distant** ; il faut donc un port
distinct plutôt que détourner celui de lecture.

On livre une commande `market:sync-prices` calquée sur `market:sync-sectors`, avec le write-side
et le port de fetch qui lui manquent.

## Décisions (validées)

- **Plage par défaut : rattrapage depuis le dernier prix stocké.** Pour chaque instrument,
  `start` = date du dernier `Price` en base, sinon `today - 1 an`. Un run manqué (panne,
  week-end) se rattrape au run suivant sans intervention.
- **Périmètre : tous les instruments Marché.** `findAll()` puis filtre `ticker != null` et
  `PriceFeedPort::supports($type)`. Même règle que `SyncAssetSectors`. Pas de couplage vers
  `Holding` (contexte Portfolio).
- **Nouveau port `PriceFeedPort`** dédié au fetch distant, orienté ticker comme
  `SectorProviderPort`. `PriceProviderPort` reste inchangé : Valuation, InstrumentView et
  Portfolio ne sont pas touchés.
- **Fetch en lot** via `fetch_prices_bulk.py` : un seul boot Python + import yfinance pour tout
  le catalogue, au lieu de N.
- **Upsert qui écrase** sur `(asset_id, date)`. yfinance révise parfois les clôtures ; le
  re-fetch du dernier jour stocké les capte.
- **`exit 1` seulement si tout échoue.** Distingue « Yahoo est down » de « un ticker est
  pourri ».
- **Correction incluse : `end` est exclusif chez yfinance.** `YahooFinanceAdapter` perd
  aujourd'hui la clôture du jour demandé, dans `getCurrentPrice()` comme dans
  `getPriceHistory()`.

Hors périmètre : pas de déclenchement depuis l'UI, pas de job en file, pas de source de prix
autre que Yahoo, pas de sync des métadonnées d'instrument (`findBySymbol` existe déjà), pas de
migration de schéma.

## Architecture

### Flux

```
market:sync-prices [--asset=ID] [--since=Y-m-d]
  │
  ├─ SyncPricesCommand ............. parse les options, affiche, code de sortie
  │
  └─ SyncAssetPrices (Action)
       ├─ InstrumentRepositoryContract::findAll() / findById()
       ├─ filtre : ticker != null && PriceFeedPort::supports(type)
       ├─ PriceRepositoryContract::latestForAsset() ...... calcule start par actif
       ├─ PriceFeedPort::fetchPrices([PriceRequestData…]) . un process python
       └─ PriceRepositoryContract::upsertForAsset() ....... write-side, par actif
```

### Port de fetch — `app/Contexts/Market/Ports/PriceFeedPort.php`

```php
interface PriceFeedPort
{
    public function supports(InstrumentType $type): bool;

    /**
     * Fetch daily prices for several tickers at once.
     *
     * @param  array<int, PriceRequestData>  $requests
     * @return array<string, array<int, PriceData>> prices keyed by ticker
     *
     * @throws PriceFeedException when the whole fetch fails
     */
    public function fetchPrices(array $requests): array;
}
```

Un ticker absent du tableau retourné signifie « aucune barre sur la plage » — week-end, jour
férié, ou ticker mort. Le script bulk n'émet une clé que si l'historique est non vide, donc les
deux cas sont indistinguables côté PHP. L'échec global, lui, est explicite : exception.

`PriceFeedException` vit à côté de son port, en `app/Contexts/Market/Ports/PriceFeedException.php`,
comme `PythonProcessException` à côté de `PythonRunner` dans `app/Shared/Python`.

### Requête — `app/Contexts/Market/Datas/PriceRequestData.php`

```php
readonly class PriceRequestData
{
    public function __construct(
        public string $ticker,
        public string $startDate,  // 'Y-m-d', inclusif
        public string $endDate,    // 'Y-m-d', inclusif côté port
    ) {}
}
```

Objet de valeur nu : la traduction vers les paramètres du script Python appartient à
l'adaptateur, puisque c'est lui qui connaît la convention de son fournisseur.

`endDate` est **inclusif dans le contrat du port**. L'adaptateur Yahoo ajoute un jour avant de
le passer au script, puisque yfinance exclut `end`.

### Rapport — `app/Contexts/Market/Datas/PriceSyncReportData.php`

```php
readonly class PriceSyncReportData
{
    /**
     * @param  array<string, int>  $synced  nombre de prix écrits, par ticker
     * @param  array<int, string>  $failed  tickers en échec
     */
    public function __construct(
        public array $synced = [],
        public array $failed = [],
    ) {}

    public function total(): int;        // synced + failed
    public function syncedCount(): int;  // count($this->synced)
    public function isTotalFailure(): bool; // total() > 0 && syncedCount() === 0
}
```

### Write-side — `PriceRepositoryContract`

```php
/**
 * Insert or update the daily prices of an asset.
 *
 * @param  array<int, PriceData>  $prices
 * @return int number of rows written
 */
public function upsertForAsset(int $assetId, array $prices): int;
```

Implémentation `EloquentPriceRepository` :

```php
Price::query()->upsert($rows, ['asset_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);
```

`unique(['asset_id', 'date'])` existe depuis la création de la table
(`2026_02_21_000944_create_security_prices_table.php:24`, colonne renommée en `asset_id` par
`2026_05_09_150000_*`). **Aucune migration nécessaire.**

### Adaptateur — `YahooFinanceAdapter`

Ajoute `implements PriceFeedPort` (l'adaptateur porte déjà `InstrumentProviderPort`,
`PriceProviderPort`, `SectorProviderPort` ; `supports()` est commun et reste inchangé :
`Stock` et `ETF`).

```php
public function fetchPrices(array $requests): array
{
    $result = $this->python->run(YahooScript::PricesBulk->path(), [
        'tickers' => array_map(fn (PriceRequestData $r) => [
            'ticker' => $r->ticker,
            'start_date' => $r->startDate,
            'end_date' => Carbon::parse($r->endDate)->addDay()->format('Y-m-d'),
        ], $requests),
    ]);

    // ! $result->ok() -> PriceFeedException
    // sinon : array<string, array<int, PriceData>> via PriceData::fromArray()
}
```

`YahooScript` gagne `case PricesBulk = 'fetch_prices_bulk.py';`. Le script Python n'est pas
modifié : il accepte déjà `{"tickers": [{ticker, start_date, end_date}, …]}` et regroupe les
tickers par plage identique pour un `yf.download` groupé. Avec le rattrapage, les actifs à jour
partagent la même plage et les actifs sans historique partagent la fenêtre d'un an : le
regroupement joue en pratique.

#### Correction de `end` exclusif

`DatabaseAssetPriceAdapter::getPriceHistory()` filtre `date <= $endDate` : dans le contrat de
`PriceProviderPort`, `endDate` est inclusif. `YahooFinanceAdapter` passe la valeur brute à
yfinance, qui exclut `end`, et perd donc le dernier jour. Corrections :

- `getPriceHistory()` : `$endDate ??= now()->format('Y-m-d')` conservé, puis `+1 jour` au
  moment de construire les paramètres du script.
- `getCurrentPrice()` : même `+1 jour`, sinon la clôture du jour n'est jamais retournée.

Les deux implémentations du port s'alignent. `YahooFinanceAdapterTest` assertait les paramètres
envoyés au runner : ses attentes changent.

### Action — `app/Contexts/Market/Actions/SyncAssetPrices.php`

```php
class SyncAssetPrices
{
    private const FALLBACK_MONTHS = 12;

    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private PriceFeedPort $feed,
        private PriceRepositoryContract $prices,
    ) {}

    public function __invoke(?int $assetId = null, ?string $since = null): PriceSyncReportData;
}
```

Déroulé :

1. `instrumentsToSync($assetId)` — même structure privée que `SyncAssetSectors` :
   `findAll()`, ou `findById()` dans une collection d'un élément, ou collection vide.
2. Filtre `ticker !== null && $this->feed->supports($instrument->type)`.
3. Pour chaque instrument retenu, `startDateFor($instrument, $since)` :
   `$since` s'il est fourni, sinon la `date` du `latestForAsset()`, sinon
   `now()->subMonths(self::FALLBACK_MONTHS)`. `endDate` = `now()`.
4. Un seul `fetchPrices()` avec toutes les requêtes.
5. Par instrument : `upsertForAsset()` avec les `PriceData` de son ticker (tableau vide si le
   ticker est absent du retour), et `synced[$ticker] = $count`.
6. `PriceFeedException` → tous les tickers dans `failed`, `synced` vide.

Deux instruments peuvent partager un ticker : le tableau `synced` est indexé par ticker, la
dernière écriture gagne. Cas non traité, cohérent avec `SyncAssetSectors`.

### Commande — `app/Contexts/Market/Console/SyncPricesCommand.php`

```php
protected $signature = 'market:sync-prices
                        {--asset= : Identifiant d\'un seul actif à synchroniser}
                        {--since= : Date de début forcée (Y-m-d), sinon reprise au dernier prix connu}';

protected $description = 'Récupère les prix quotidiens des instruments auprès du fournisseur de marché';
```

Sortie et codes de retour :

| Cas | Traitement | Sortie |
| --- | --- | --- |
| Aucun instrument éligible | avertissement | `Aucun instrument à synchroniser.` + `SUCCESS` |
| Ticker avec des barres | upsert, compte les lignes | `PE500.PA : 2 prix` |
| Ticker absent du retour | `0 prix`, pas un échec | `DEAD.PA : 0 prix` |
| `PriceFeedException` | tous en échec | lignes `échec` + `FAILURE` |

```
PE500.PA : 2 prix
AAPL     : 253 prix
DEAD.PA  : 0 prix

3 instruments, 3 synchronisés, 0 échec
```

`FAILURE` uniquement si `isTotalFailure()` — instruments éligibles > 0 et zéro synchronisé.

### Câblage

`MarketProvider::registers()` gagne un paramètre nommé `priceFeed`, binde
`PriceFeedPort::class`. `AppServiceProvider` passe `priceFeed: YahooFinanceAdapter::class`
(`priceProvider: DatabaseAssetPriceAdapter::class` inchangé).

`bootstrap/app.php` :

```php
->withCommands([
    SyncPricesCommand::class,
    SyncSectorsCommand::class,
])
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('market:sync-prices')->dailyAt('23:30');
    $schedule->command('market:sync-sectors')->weekly();
})
```

23h30 : après la clôture US (22h CET) comme celle d'Euronext. Le bloc TODO de quatre lignes
disparaît.

`docs/refactor-status.md` : ligne « Couche tâche planifiée (commande `securities:*`) » du
tableau Market passe à `Fait` avec le nom réel des commandes ; la ligne scheduler du tableau de
câblage passe de « Neutralisé » à l'état réel.

## Tests

Tests colocalisés en `*Test.php` à côté des sources, convention de tout le contexte Market. TDD :
test rouge avant implémentation.

| Test | Couvre |
| --- | --- |
| `Console/SyncPricesCommandTest.php` | une ligne par ticker + résumé ; `SUCCESS` sur succès partiel ; `FAILURE` si tout échoue ; avertissement si aucun instrument ; `--asset` restreint le périmètre ; `--since` transmis |
| `Actions/SyncAssetPricesTest.php` | `start` = date du dernier prix stocké ; fallback 12 mois sans historique (`travelTo`) ; `--since` prioritaire ; instruments sans ticker ou non supportés exclus ; `upsertForAsset` appelé par actif ; `PriceFeedException` → tous en échec |
| `Infrastructure/YahooFinanceAdapterTest.php` | paramètres envoyés au script bulk (dont `end_date` décalé de +1 jour) ; mapping du retour par ticker en `PriceData` ; `status: error` → `PriceFeedException` ; `getPriceHistory` et `getCurrentPrice` incluent bien la borne de fin |
| `Infrastructure/EloquentPriceRepositoryTest.php` | `upsertForAsset` insère, puis écrase sur `(asset_id, date)` sans doublon ; valeur de retour |
| `Datas/PriceRequestDataTest.php`, `Datas/PriceSyncReportDataTest.php` | construction, `toArray()`, `total()` / `syncedCount()` / `isTotalFailure()` |
| `MarketProviderTest.php` | `PriceFeedPort` résolu vers l'implémentation passée |

L'action et la commande se testent avec `$this->mock(PriceFeedPort::class)`, l'adaptateur avec
`FakePythonRunner` — comme `SyncSectorsCommandTest` et `YahooFinanceAdapterTest` aujourd'hui.
Aucun appel réseau dans la suite.
