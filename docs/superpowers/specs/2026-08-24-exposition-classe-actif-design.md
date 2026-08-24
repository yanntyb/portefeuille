# Exposition et enveloppe : deux axes pour les instruments

Date : 2026-08-24

## Problème

`InstrumentType` mélange deux axes orthogonaux sous un seul enum :

- **l'enveloppe** — comment l'actif est détenu : titre vif, ETF, ETC, contrat à terme ;
- **l'exposition** — à quoi le porteur est exposé : actions, obligations, matières premières, crypto.

`ETF` est la preuve du mélange : c'est une enveloppe rangée parmi des expositions. Les sept ETF du
portefeuille (World, S&P 500 ×2, Nasdaq-100, Stoxx 600, Emerging) portent tous de l'exposition
actions ; ils devraient grossir la ligne « Actions », pas former une catégorie à côté d'elle.
Symétriquement, `Or (Xetra-Gold)` et `Argent (contrat à terme)` sont deux enveloppes très
différentes pour une seule exposition.

Conséquence visible : le tableau de bord affiche trois classes de patrimoine — Actions, Immobilier,
Crypto — où « Actions » agrège en réalité actions, ETF, obligations et matières premières. Sur les
91 133 € du portefeuille, la répartition réelle est Actions ≈ 88 %, Matières premières 6,3 %
(5 725 €), Crypto 5,8 % (5 252 €). Deux expositions sur trois sont invisibles, et la part affichée
pour « Actions » est fausse de six points.

Le partage actuel se lit dans `InstrumentType::securities()` et `isCrypto()` — « tout sauf la
crypto » d'un côté, la crypto de l'autre. Ce prédicat ne peut pas exprimer une troisième classe
sans devenir une liste d'exclusions qui grandit à chaque ajout.

## Objectifs

- Lire la répartition patrimoniale par **exposition**, pas par enveloppe.
- Ajouter une classe d'actif sans écrire de classe PHP : la dériver de la donnée.
- Donner à chaque actif **une seule adresse** (`/asset/{id}`), quelle que soit son exposition.
- Conserver l'enveloppe là où elle décide réellement : badge de ligne, disponibilité des secteurs.

## Non-objectifs

- **Sous-classer une exposition.** Pas de « Actions US / Europe », pas de « Or / Argent » : la
  granularité s'arrête à l'exposition.
- **Revenu obligataire.** `Bond` n'aura pas de ligne de revenu (voir « Le piège du double
  comptage »).
- **Redirections des anciennes URL.** `/instruments/{id}` et `/crypto/{id}` disparaissent sans 301.
  Application personnelle ; le cache du service worker se réaligne au premier chargement en ligne.
- **Renommer les cas de `InstrumentType`.** L'enveloppe garde ses noms actuels, y compris
  `Commodity` et `Bond`, qui sont à la fois enveloppe et exposition. Le renommage coûterait une
  migration de données pour un gain de vocabulaire.

## Décisions

| Décision | Retenu | Écarté |
| --- | --- | --- |
| Porter l'exposition | Colonne `assets.asset_class`, castée en enum `AssetClass` | Dériver l'exposition de `InstrumentType` par un `match` (`ETF => Equity` en dur : faux au premier ETF obligataire, et silencieux) |
| Sort de `InstrumentType` | Conservé comme axe enveloppe | Remplacé par l'exposition (perte du badge `typeLabel` et de `supportsSectors()`) |
| Rang des classes au résumé | Une ligne par **exposition** | Une ligne par enveloppe : « ETF » se comparerait à « Immobilier » |
| Nombre d'adaptateurs `AssetClassPort` | Un seul, paramétré par `AssetClass`, plus `RealEstateClass` | Une classe PHP par exposition (quatre classes quasi identiques) |
| Adresse d'une fiche | `/asset/{id}`, contrôleur unique, vue polymorphe | Une route de fiche par exposition, avec 404 croisés entre elles |
| Adresse d'une liste | Une route par exposition (`/actions`, `/obligations`, `/matieres-premieres`, `/crypto`) | Une liste unique filtrée côté client (`AssetClassPort::href()` n'aurait plus de destination) |
| Teinte d'une classe au graphe | Jeton porté par le payload, résolu par thème dans `palette()` | Index de position dans `classColors()` (collision dès la quatrième classe) |

## Le modèle

### `AssetClass`

Nouvel enum dans `app/Contexts/Market/Enums/`, même convention que `InstrumentType`
(`values()`, `getLabel()`, `getColor()`, `getIcon()`), plus `slug()`.

| Cas | Valeur | Libellé | Slug |
| --- | --- | --- | --- |
| `Equity` | `equity` | Actions | `actions` |
| `Bond` | `bond` | Obligations | `obligations` |
| `Commodity` | `commodity` | Matières premières | `matieres-premieres` |
| `Crypto` | `crypto` | Crypto | `crypto` |

Slugs en français : l'URL est du texte vu par l'utilisateur. Valeurs en anglais, comme celles de
`InstrumentType`, parce qu'elles sont stockées en base.

**L'ordre des cas est un contrat**, à écrire dans le docbloc de l'enum : il fixe l'ordre des lignes
du résumé et l'empilement des bandes du graphe. Il ne fixe plus les couleurs — celles-ci sont
nommées cas par cas.

Pas de `incomeSource()` sur cet enum : `Market` ne doit pas dépendre d'`Income`.

### Colonne et renseignement par défaut

`assets` gagne `asset_class`, chaîne castée en `AssetClass`. Le discriminant marché / hors-marché
reste `type` : le global scope de `Instrument` (`whereIn('type', InstrumentType::values())`) ne
bouge pas.

`AssetClass::defaultForType(InstrumentType): self` porte la correspondance :

| `InstrumentType` | `AssetClass` |
| --- | --- |
| `Stock` | `Equity` |
| `ETF` | `Equity` |
| `Bond` | `Bond` |
| `Commodity` | `Commodity` |
| `Crypto` | `Crypto` |

Cette méthode est appelée à deux endroits et **définie une seule fois** :

1. le hook `creating` d'`Instrument`, en miroir de celui qui existe déjà (`type ??= Stock`) : il ne
   renseigne `asset_class` que si elle est absente. Les huit seeders et `InstrumentFactory` restent
   intacts.
2. le rétro-remplissage de la migration, sur les 36 lignes existantes — 24 actions + 7 ETF →
   `Equity`, 2 → `Commodity`, 3 → `Crypto`.

**Ce qui distingue ce défaut d'une dérivation** : la valeur est écrite en base une fois, puis
corrigible ligne à ligne. Un `match` permanent ne l'aurait jamais été.

**Limite assumée et nommée** : un futur ETF obligataire ou aurifère synchronisé depuis Yahoo
atterrira en `Equity` sans rien signaler. Parade écartée — faire échouer `AssetSyncCommand` sur un
ETF sans exposition explicite : une exception au milieu d'une synchronisation coûte plus cher
qu'une ligne à corriger, sur une application où les instruments sont ajoutés à la main. Un test
fige la correspondance ; sa modification devient donc délibérée.

### Invariant

Le patrimoine total est la somme des classes. Tout instrument détenu porte donc **exactement une**
exposition, et rien n'est nullable en lecture.

## Le contexte `Wealth`

`AssetClassPort` ne change pas. Ce qui change, c'est qui l'implémente.

### Un adaptateur paramétré

`PortfolioAssetClass` cesse d'être abstraite et prend `AssetClass $exposure` en constructeur.
`key()`, `label()` et `href()` se lisent sur l'enum ; `types()` disparaît au profit d'un filtre par
exposition. `SecuritiesClass` et `CryptoClass` sont supprimées.

`RealEstateClass` reste écrite à la main. C'est ce contraste qui justifie le port : deux
adaptateurs qui n'ont rien en commun sauf l'interface.

### Le registre se construit

`AssetClassRegistry` est bâti depuis `AssetClass::cases()`, puis `RealEstateClass`. Le conteneur ne
sait pas résoudre un `AssetClass` en paramètre de constructeur, donc `WealthProvider::registers()`
ne prend plus une liste de noms de classes : c'est le registre qui instancie.

L'ordre reste un contrat, mais il déménage — d'un tableau dans `AppServiceProvider` vers l'ordre
des cas de l'enum.

### Le piège du double comptage

`monthlyIncomeFor()` filtre par `IncomeSource`, jamais par exposition. Si `Equity` **et** `Bond`
renvoyaient toutes deux `IncomeSource::Dividend`, les mêmes dividendes seraient comptés deux fois
dans le revenu du patrimoine — exactement le bug que le docbloc actuel évite pour les loyers.

Règle : **une exposition, une origine, au plus une fois.**

| Exposition | Origine |
| --- | --- |
| `Equity` | `IncomeSource::Dividend` — « Dividendes » |
| `Bond` | aucune |
| `Commodity` | aucune |
| `Crypto` | aucune |

L'absence de ligne obligataire est cohérente avec l'état réel : le seul producteur est
`DividendProjector` sur `asset_dividends`, que `YahooFinanceAdapter::covers()` ne remplit pas pour
les obligations. Une ligne « Coupons » afficherait zéro.

Le jour où une source de coupons existera, il faudra ajouter un filtre par exposition à la lecture
de revenu (`GetIncomeSummary`, `IncomeSourceRegistry`). Hors périmètre ici.

### La correspondance exposition → origine, définie une fois

`Income` dépend déjà de `Market` (`MarketDividendHistory`). `IncomeSource::forAssetClass(AssetClass): ?self`
y trouve donc sa place. `Wealth` et `MarketView` la lisent tous les deux ; personne ne la recopie.

### Classes vides

Le portefeuille ne contient aucune obligation. Le registre déclare la classe quand même :
`assetClassWeights()` écarte déjà les lignes à zéro et l'infobulle du graphe tait les bandes nulles.
Aucun traitement particulier.

### Mémoïsation

Quatre expositions font quatre `GetPortfolioOverview` — quatre requêtes et quatre lectures de prix
sur les mêmes 36 lignes. Un lecteur mémoïsé à la requête (le registre est déjà `scoped()`) lit une
fois et découpe en mémoire.

Pas de mémoïsation sur `BuildEvolutionSeries` : `SeriesCachePort` sert déjà les quatre appels sans
reconstruire, il ne resterait que quatre désérialisations à économiser. Le gain n'achète pas son
coût.

## Signatures qui changent

Le filtre par classe traverse trois contextes. Il passe de `?list<InstrumentType>` à
`?list<AssetClass>` :

- `Portfolio\Actions\GetPortfolioOverview(User $user, ?array $classes)`
- `Valuation\Actions\BuildEvolutionSeries(int $userId, ?int $months, ValuationGranularity, ?array $classes)`
- `Valuation\Actions\BuildPortfolioPerformances(int $userId, ?array $classes)`
- `Valuation\Ports\InstrumentDirectoryPort::idsOfTypes()` → `idsOfClasses()`
- `MarketView\Actions\GetHoldingTrends(int $userId, ValuationRange, ?array $classes)` — nouveau
  paramètre, voir « Instantané ».

`Portfolio\Datas\HoldingLineData` gagne `assetClass` et conserve `type` / `typeLabel` : la ligne
affiche l'enveloppe, la page groupe par exposition.

`InstrumentType::securities()` et `InstrumentType::isCrypto()` sont supprimés.

**Rappel de `.ai/rules/contexts.md`, toujours valable** : la série d'évolution se filtre **après**
son cache (elle porte `assetId` par actif), les performances **avant** et sous un nom de cache
distinct — une fenêtre glissante agrège les transactions, elle ne se découpe pas après coup. Le
passage à `AssetClass` ne change pas ce partage ; il faut le conserver tel quel.

## Routes et pages

### Listes — un contrôleur, quatre routes engendrées

```php
foreach (AssetClass::cases() as $class) {
    Route::get($class->slug(), AssetClassController::class)
        ->defaults('exposure', $class->value)
        ->name("classes.{$class->value}");
}
```

Routes explicites plutôt qu'un `/{exposure}` attrape-tout : la racine porte déjà `/asset`,
`/properties`, `/hors-ligne`, `/instantane`, `/manifest.json`, `/sw.js`. `defaults()` évite
d'inventer une résolution slug → enum ; la valeur arrive comme paramètre de route et le contrôleur
fait `AssetClass::from(...)`.

`AssetClassController` reprend la composition de `InstrumentsController`, props différées
comprises, avec deux gates :

- **Secteurs** — sur l'**enveloppe** : rendu si l'exposition peut contenir des `Stock` ou des `ETF`,
  donc `Equity` seule aujourd'hui.
- **Revenus** (`income` + `annualIncome`) — `IncomeSource::forAssetClass()` non nul.

`GetSectorBreakdown($user)` calcule sur tout le portefeuille, sans filtre. Sur la page Actions
c'est juste **par accident** : seuls `Stock` et `ETF` portent des lignes de secteur, l'or et la
crypto n'y pèsent rien. Laissé tel quel, mais noté pour ne pas laisser croire que c'est filtré.

### Fiche — `/asset/{id}`, polymorphe

`AssetController` remplace `InstrumentDetailController` et `CryptoDetailController`. 404 sur actif
inconnu, plus jamais sur « actif de l'autre classe » : un actif a une adresse, pas deux.

Section Dividendes rendue si `IncomeSource::forAssetClass()` est non nul — c'est déjà ce que
`InstrumentPageSnapshot` modélise avec son `dividends?` optionnel (renommé `AssetPageSnapshot`,
voir « Instantané »). Fil d'Ariane déduit de
l'exposition de l'actif : libellé + slug.

### Pages Vue

- `Instruments/Index.vue` + `Crypto/Index.vue` → `AssetClass/Index.vue`
- `Instruments/Show.vue` + `Crypto/Show.vue` → `Asset/Show.vue`

`InstrumentList.vue` perd sa prop `base-path` : toutes les lignes pointent vers `/asset/{id}`, il
n'y a plus de racine à choisir.

### Ce qui disparaît

`InstrumentsController`, `CryptoController`, `InstrumentDetailController`,
`CryptoDetailController`, `SecuritiesClass`, `CryptoClass`, `InstrumentType::securities()`,
`InstrumentType::isCrypto()`, `classColors()`, `classColorAt()`, et les deux pièges de
`.ai/rules/contexts.md` que ces contrôleurs portaient.

## Instantané hors-ligne

`BuildMarketViewSnapshot` perd son partage : `detailsByClass()` disparaît, une seule boucle sur les
positions produit `assets`, sans décider à quelle classe chaque fiche appartient.

```ts
export interface Snapshot {
    version: string;
    generatedAt: number;
    dashboard: DashboardSnapshot;
    classes: Record<string, AssetClassListSnapshot>;
    assets: Record<string, AssetPageSnapshot>;
    properties: { list: PropertiesListSnapshot; byId: Record<string, PropertyPageSnapshot> };
}
```

`InstrumentPageSnapshot` est renommé `AssetPageSnapshot` — il ne décrit plus « la fiche d'un
instrument de telle classe » mais la fiche unique servie par `/asset/{id}`.
`InstrumentsListSnapshot` et `CryptoListSnapshot` fusionnent en `AssetClassListSnapshot`, où le trio
`sectorBreakdown?` / `income?` / `annualIncome?` devient optionnel — miroir exact des gates du
contrôleur. `emptyList(bool $forSecurities)` prend une `AssetClass` au lieu d'un booléen et lit les
mêmes gates : le cas « base vide » cesse d'avoir sa propre définition de qui porte quoi.

Côté store : `instrumentsList` / `cryptoList` → `classList(AssetClass)`,
`instrumentPage(id)` / `cryptoPage(id)` → `assetPage(id)`.

`swCache.ts` ne connaît que `SNAPSHOT_PATH` et le préfixe de build : aucune liste de routes à suivre.

**Gonflement à éviter.** `GetHoldingTrends($userId, $range)` ne filtre rien : il rend les tendances
de toutes les positions, et les deux pages en reçoivent aujourd'hui une copie entière. À quatre
classes, l'instantané porterait quatre fois le portefeuille complet. D'où le nouveau paramètre
d'exposition. Aucun changement à l'écran — les pages apparient déjà par `assetId` et ignorent le
surplus.

## Couleurs

`AssetClass::getColor()` rend un **jeton** (`'blue'`, `'amber'`…), pas un hexadécimal : `palette()`
en tient deux jeux, clair et sombre, et ECharts ne résout pas les variables CSS.

Donc : le payload porte le jeton (`AssetClassData`, `ClassValuesData`, `WealthStackClass` gagnent
`color`), `palette()` gagne la correspondance jeton → teinte par thème, et `classColors()` /
`classColorAt(index)` disparaissent.

C'est ce qui règle le commentaire actuel de `chart.ts` — « le cas ne se produit qu'à partir d'une
quatrième classe » — alors qu'on en aurait cinq. Plus d'index, plus de collision ; l'ordre du
registre ne décide plus que de l'empilement.

Le reste du front suit sans changement : `assetClassWeights()` est déjà générique sur
`overview.classes`, `Dashboard.vue` aussi, et les libellés viennent du serveur.

## Tests

### Neufs

- `AssetClassTest` — valeurs, libellés, slugs, `defaultForType()`, et l'ordre des cas comme contrat
  explicite.
- `InstrumentTest` — le hook `creating` renseigne `asset_class` depuis `type`, et **n'écrase pas**
  une valeur fournie.
- **Invariant** — somme des classes = patrimoine total ; somme des origines de revenu = revenu
  mensuel, sans doublon. Sans ce test, un futur `Bond → Dividend` repasserait inaperçu.
- `AssetClassControllerTest` — une route par exposition ; gates vérifiées : `Equity` porte secteurs
  et revenus, `Crypto` ni l'un ni l'autre.
- `AssetControllerTest` — fiche polymorphe ; dividendes présents sur `Equity`, absents sur `Crypto`
  et `Commodity` ; 404 sur actif inconnu.

### À adapter

`AssetClassRegistryTest`, `GetWealthOverviewTest`, `BuildWealthSeriesTest`, `GetWealthIncomeTest`,
`GetPortfolioOverviewTest`, `BuildEvolutionSeriesTest`, `BuildPortfolioPerformancesTest`,
`BuildMarketViewSnapshotTest`, `SnapshotControllerTest`, `InstrumentTypeTest`,
`MarketInstrumentDirectoryTest`, et côté front `wealth.test.ts`, `chart.test.ts`,
`chart.dark.test.ts`, `snapshot.test.ts`.

### À supprimer (accord donné)

`InstrumentDetailControllerTest` et `CryptoDetailControllerTest`. Ils couvrent le 404 croisé entre
les deux fiches ; avec `/asset/{id}` ce comportement n'existe plus. Il n'y a rien à conserver, pas
seulement rien à tester. Remplacés par `AssetControllerTest`.

## Découpage des commits

Un commit par étape, chacun vert.

1. **`AssetClass` et la donnée** — enum, colonne, `defaultForType()`, hook `creating`, migration et
   rétro-remplissage, tests de modèle. Rien d'autre ne bouge : l'application tourne encore sur
   `InstrumentType::securities()`.
2. **Les filtres passent en `AssetClass`** — `idsOfClasses()`, les trois signatures d'action,
   `HoldingLineData`. Effet inchangé pour les appelants existants.
3. **`MarketView`** — `AssetClassController` et routes engendrées, `AssetController` sur
   `/asset/{id}`, suppression des quatre contrôleurs, fusion des quatre vues en deux.
4. **Instantané** — `BuildMarketViewSnapshot`, `SnapshotController`, contrat TS, store, filtre de
   `GetHoldingTrends`.
5. **`Wealth`** — `PortfolioAssetClass` paramétrée, registre dérivé, suppression de
   `SecuritiesClass` et `CryptoClass`, mémoïsation de l'aperçu. Le registre déclare cinq classes ;
   le résumé en affiche quatre, les obligations étant à zéro. C'est ici que le changement se voit.
6. **Couleurs** — jeton dans le payload, correspondance dans `palette()`, suppression de
   `classColorAt()`.
7. **Règles** — réécriture de `.ai/rules/contexts.md` autour du nouvel axe, ajout d'une
   `.ai/rules/market.md`.

`MarketView` passe avant `Wealth` volontairement : le tableau de bord ne doit pas pointer vers des
routes qui n'existent pas encore.

## Règles à enregistrer

- **`app/Contexts/Market/**`** — Deux axes, pas un. `InstrumentType` dit **comment** l'actif est
  détenu (badge de ligne, `supportsSectors()`), `AssetClass` dit **à quoi** le porteur est exposé
  (classe de patrimoine, pages liste, filtres). Tout filtre par classe passe par `AssetClass` ;
  `InstrumentType` ne sert plus jamais à partager le portefeuille. `AssetClass::defaultForType()`
  est un défaut au moment de la création, pas une dérivation : la valeur stockée fait foi.
- **`app/Contexts/**`** — Réécriture de la règle existante : le partage par classe se lit dans
  `AssetClass`, les deux pièges de cache (`BuildEvolutionSeries` filtre après, les performances
  avant sous un nom distinct) restent valables, et le piège du 404 croisé disparaît avec
  `/asset/{id}`.
- **`app/Contexts/Wealth/**`** — Une exposition, une origine de revenu, au plus une fois :
  `monthlyIncomeFor()` filtre par `IncomeSource` et non par exposition, donc deux expositions
  partageant une origine compteraient le même revenu deux fois.
