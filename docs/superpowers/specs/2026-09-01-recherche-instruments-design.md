# Recherche d'instruments

Le catalogue de l'application est figé. Les trente instruments qu'elle connaît viennent de
`InstrumentCatalogSeeder` — une constante `INSTRUMENTS` en dur — complétés par ce que
`BackupSeeder` restaure du snapshot de production. Aucune route ne crée d'instrument : les trois
seules écritures exposées portent sur les transactions, plus la confirmation d'un dividende.
Acheter un titre absent de cette liste est donc impossible sans toucher au code et rejouer un
seeder.

Le plus frustrant est que la brique manque à peine. `search_ticker.py` interroge déjà
`yf.Search` et rend symbole, nom, place de cotation et type ; `YahooFinanceAdapter::findBySymbol()`
l'appelle. Mais **personne n'appelle `findBySymbol()`** — ni une action, ni une commande, ni un
contrôleur. Le chemin existe de bout en bout et ne mène nulle part.

Ce chantier ouvre la première écriture d'instrument de l'application : chercher chez Yahoo,
confirmer les métadonnées, créer la ligne, puis remplir son historique en arrière-plan.

## Périmètre

Dedans :

- une recherche d'instruments servie en JSON, base d'abord puis Yahoo ;
- un écran de confirmation des métadonnées avant création — jamais de création à l'aveugle ;
- la création elle-même, validée par un FormRequest, suivie d'un job de synchronisation ;
- une modale unique montée à deux endroits : le catalogue d'une exposition, et le formulaire de
  transaction ;
- cinq ans d'historique — cours, secteurs, dividendes — mis en file dès la création.

Dehors, explicitement :

- **la suppression ou la correction d'un instrument.** Créer suffit ; corriger un type mal deviné
  reste un travail de base de données, comme aujourd'hui pour un ETF obligataire ;
- **les obligations.** `YahooFinanceAdapter::covers()` les exclut déjà, Yahoo ne les cote pas.
  Un résultat de recherche typé obligation n'arrivera pas, et rien ne le simule ;
- **un index unique sur `assets.ticker`.** La protection contre les doublons est la recherche
  locale, en amont ; poser la contrainte demanderait de modifier la migration de création et de
  reconstruire la base. À faire le jour où un doublon passe malgré tout ;
- **une recherche globale** dans le bandeau de l'application. Les deux points d'entrée retenus
  sont ceux où le besoin naît.

## Le port et son adaptateur

`InstrumentProviderPort` gagne une méthode :

```php
/** @return list<InstrumentSearchResultData> */
public function searchInstruments(string $query): array;
```

Elle ne remplace pas `findBySymbol()`, elle répond à un autre besoin. `findBySymbol()` prend le
`InstrumentType` **en entrée** et ne garde que le premier résultat : elle résout un symbole déjà
connu. Ici le type est précisément ce qu'on cherche à apprendre, et les N résultats sont le
matériau de l'écran de choix.

`findBySymbol()` reste en place, toujours sans appelant. Sa suppression est un nettoyage à part.

Nouvelle Data, `Market\Datas\InstrumentSearchResultData` :

```php
readonly class InstrumentSearchResultData
{
    public function __construct(
        public string $symbol,
        public string $name,
        public ?string $exchange,
        public ?InstrumentType $type,
        public ?int $existingId = null,
    ) {}
}
```

Distincte d'`InstrumentData`, qui décrit un instrument **résolu** — type non nul, allocations
sectorielles chargées. Un résultat de recherche est plus pauvre par nature : Yahoo peut rendre un
`typeDisp` que l'application ne sait pas traduire, et aller chercher les secteurs de chaque ligne
d'une liste de résultats coûterait un aller-retour Python par ligne.

`YahooFinanceAdapter::searchInstruments()` lance `YahooScript::Search` (le script Python est
inchangé) et traduit chaque `typeDisp` :

| `typeDisp` Yahoo | `InstrumentType` |
| --- | --- |
| `Equity` | `Stock` |
| `ETF` | `ETF` |
| `Cryptocurrency` | `Crypto` |
| `Future` | `Commodity` |
| tout autre | `null` |

Un type `null` n'écarte pas le résultat : il s'affiche, et l'écran de confirmation demande à
l'utilisateur de trancher. En cas d'échec du script, la méthode rend un tableau vide, comme les
autres méthodes de l'adaptateur.

## Les routes

Deux routes neuves, dans `Market\Http` — le contexte propriétaire de la table `assets` — aux côtés
de `StartSyncController`.

| Route | Rôle |
| --- | --- |
| `GET /instruments/recherche?q=` | JSON. Cherche en base, puis chez Yahoo. |
| `POST /instruments` | Crée l'instrument. JSON en réponse. |

### La recherche cherche en base d'abord

`SearchInstrumentsController` interroge d'abord `assets` (`LIKE` sur `ticker` et `name`), puis
Yahoo. Les deux listes fusionnent sur le symbole : un instrument déjà connu porte son `existingId`,
et n'est pas créable une seconde fois.

C'est la seule protection contre les doublons — `assets.ticker` n'a pas d'index unique. Elle est
suffisante parce que l'utilisateur voit la ligne existante avant de cliquer : on lui propose
d'ouvrir sa fiche (`assets.show`) ou, dans le formulaire de transaction, de la sélectionner
directement.

Un `q` vide rend une liste vide sans appeler Python.

### La création répond en JSON, pas par une redirection

Les routes d'écriture de l'application répondent toutes par une redirection, que le client rend
partielle. `POST /instruments` déroge, et la raison est le formulaire de transaction : la modale de
recherche s'y ouvre par-dessus une saisie en cours, qui ne doit ni être perdue ni être rejouée. Le
formulaire a besoin de l'`id` créé pour le sélectionner aussitôt — un identifiant qu'une
redirection ne lui donnerait qu'au prix d'un flash ou d'un rechargement complet des options.

Conséquence assumée : les erreurs de validation reviennent en 422 JSON et sont re-mappées à la main
côté client, là où `useForm` aurait rempli `form.errors` tout seul.

### La validation

`Market\Http\InstrumentRequest`, co-localisée comme `Portfolio\Http\TransactionRequest` :

- `ticker` requis, chaîne ;
- `name` requis, chaîne ;
- `isin` nullable, chaîne ;
- `type` et `asset_class` requis, `Rule::enum` ;
- `authorize()` rend `auth()->check()`.

`CreateInstrument` (dans `Market\Actions`) écrit un tableau littéral — `Instrument` est gardé par
`$guarded = ['id']`, jamais `create($request->validated())` — puis met `SyncInstrumentJob` en file.

`asset_class` est envoyée explicitement par le client, donc le défaut posé par le hook `creating`
du modèle ne s'applique pas. C'est voulu : `AssetClass::defaultForType()` reste le défaut d'un
instrument créé sans exposition, ici l'utilisateur a vu la valeur et l'a validée.

## Le job

`Market\Jobs\SyncInstrumentJob` prend un identifiant d'instrument et synchronise cinq ans
d'historique — la profondeur d'`InstrumentCatalogSeeder`. Il enchaîne `SyncAssetPrices`,
`SyncAssetSectors` et `SyncAssetDividends`, en filtrant chacune par le `supportsX()` du port
correspondant, comme le fait déjà `SyncMarketData` : une crypto n'a pas de ventilation
sectorielle, et lui en demander une est un aller-retour Python perdu.

La fiche de l'instrument est donc atteignable immédiatement après création, mais vide — sans
cours, sans variation, sans tendance — le temps que le job passe.

## Le service worker

`/instruments/recherche` rejoint `/instantane` et `/transactions/options` dans le `passthrough` de
`classifyRequest` (`resources/js/lib/swCache.ts`). Ces réponses ne sont jamais mises en cache ni
servies périmées : la saisie est bloquée hors-ligne, une liste de résultats Yahoo rescapée du
cache n'aurait aucun sens.

`POST /instruments` est déjà en `passthrough` — `classifyRequest` y envoie toute méthode autre que
`GET`.

## La modale

`resources/js/components/instruments/InstrumentSearchDialog.vue`, deux étapes dans une seule
modale.

**Étape 1 — recherche.** Un champ et une liste. La frappe est débouncée à 300 ms et chaque requête
annule la précédente. Un résultat portant un `existingId` s'affiche marqué et ne mène pas à
l'étape 2.

**Étape 2 — confirmation.** Nom, ticker, ISIN, type et exposition, pré-remplis depuis le résultat
cliqué. Un retour ramène à l'étape 1 sans perdre le terme cherché. L'envoi passe par `useHttp`
(Inertia v3 ; axios a été retiré) — même client que `SyncButton.vue`, qui poste déjà
`/synchronisation` ainsi.

Le type pré-rempli est celui déduit du `typeDisp`, ou le premier cas de l'enum si Yahoo a rendu
quelque chose d'inconnu. L'exposition suit `AssetClass::defaultForType()` du type retenu, sauf sur
la page catalogue (voir plus bas).

## Les deux points d'entrée

### Le catalogue d'une exposition

`AssetClass/Catalog.vue` porte déjà un champ de recherche — un filtre local sur le catalogue chargé
en mémoire. On ne lui ajoute pas un second champ : c'est **l'état vide de ce filtre** qui devient
l'entrée.

```
Rechercher un instrument
[ nvidia          ]

Aucun instrument ne correspond.
> Chercher « nvidia » chez Yahoo
```

Le lien ouvre la modale déjà remplie du terme. L'ajout arrive au moment exact où le manque se
constate, et l'écran ne montre jamais deux recherches à la fois.

**Le piège de l'exposition.** La page ne vaut que pour une classe d'actif. Un instrument créé dans
une autre exposition n'apparaîtrait pas au rechargement, et l'utilisateur croirait la création
ratée. Donc :

- l'exposition se pré-remplit de celle de la page, et non de `AssetClass::defaultForType()` ;
- si l'utilisateur la change quand même, le succès ne fait pas `router.reload({ only: ['catalog'] })`
  mais une visite vers le catalogue de la classe choisie.

### Le formulaire de transaction

Sous le champ « Actif » de `TransactionForm.vue`, un lien ouvre la même modale. Au succès, le
formulaire refetch `/transactions/options` puis pointe `form.assetId` sur l'instrument créé.

Le refetch est nécessaire : la liste des instruments est servie par cette route, pas par les props
de page, et `GetTransactionFormOptions` y joint le dernier cours de chacun. Rien du reste de la
saisie n'est touché — la modale n'a jamais quitté la page.

## Tests

Co-localisés, en Pest, selon la convention du dépôt.

| Fichier | Ce qu'il fige |
| --- | --- |
| `YahooFinanceAdapterTest` | mapping `typeDisp` → `InstrumentType` ; type inconnu rendu `null` ; script en échec rendant un tableau vide |
| `SearchInstrumentsControllerTest` | base interrogée d'abord ; `existingId` posé sur un ticker connu ; Yahoo en complément ; `q` vide rendant 200 et une liste vide sans appel Python |
| `InstrumentRequestTest` | 422 sur enum invalide ou champ requis manquant ; `authorize()` faux sans session |
| `StoreInstrumentControllerTest` | création par tableau littéral ; `asset_class` envoyée respectée ; `SyncInstrumentJob` en file (`Queue::fake`) |
| `SyncInstrumentJobTest` | fenêtre de cinq ans ; `supportsX()` respectés — aucune demande de secteurs pour une crypto |
| `swCache.test.ts` | `/instruments/recherche` classée `passthrough` |
| `InstrumentSearchDialog.test.ts` | debounce ; requête précédente annulée ; résultat déjà en base non créable ; retour à l'étape 1 conservant le terme ; 422 affiché |
| `Catalog.test.ts` (nouveau — la page n'en a pas) | lien Yahoo présent dans l'état vide, terme repris ; exposition de la page pré-remplie |
| `TransactionForm.test.ts` | après création, l'actif est sélectionné et les options rechargées |
