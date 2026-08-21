# Tableau de bord patrimoine : résumé unique, une page par classe d'actif

Date : 2026-08-21
Statut : design validé (brainstorming). Fait suite à `2026-08-20-immobilier-locatif-design.md`,
dont l'intégration a rendu le tableau de bord illisible.

## Contexte

`Pages/Dashboard.vue` empile aujourd'hui sept sections de poids visuel égal, dans une colonne de
520 px : `Valorisation`, `Évolution`, `Instruments`, `Performances`, `Revenus`, `Immobilier`,
`Secteurs`. Quatre défauts, tous nés de l'arrivée de l'immobilier :

1. **Le grand chiffre ne dit pas le patrimoine.** `overview.totalValue` ne compte que les titres ;
   l'immobilier arrive en sixième position avec son propre total.
2. **L'immobilier est un corps étranger.** Un bloc inséré au milieu de cinq sections boursières,
   au même niveau de titre `h2`.
3. **Les revenus sont à deux endroits.** Les dividendes en section 5, le cash-flow locatif noyé
   dans la ligne de résumé de la section 6.
4. **Aucune hiérarchie.** Résumé, détail et analyse se lisent au même volume.

## Décisions (validées)

- Le tableau de bord sert un **coup d'œil quotidien** : résumé du patrimoine, évolution, revenus.
  Rien d'autre.
- Le détail part sur **deux pages de classe d'actif**, `/instruments` et `/properties`, atteintes
  depuis les deux lignes du résumé.
- Le graphe du tableau de bord trace le **patrimoine total en aires empilées** — titres et
  immobilier — pour que le sommet du tracé égale le grand chiffre.
- L'**investi** d'un bien acheté à crédit est le **cash réellement sorti** :
  `apport + cash injecté`. Voir la formule et sa justification plus bas.
- La valeur estimée entre deux `PropertyValuation` se lit **en escalier**, jamais interpolée.
- Le bloc Revenus donne **un net mensuel combiné** (dividendes + locatif net), puis les deux
  origines en sous-lignes.
- La péremption des séries immobilières se détecte par **empreinte lue dans les données**, pas
  par observer. Un cache, pas une table de projection.
- Le contexte s'appelle **`Wealth`**, sans suffixe `View`.

Hors périmètre : aucun nouvel onglet de navigation, aucune saisie, aucune modification du calcul
des performances ni de la répartition sectorielle.

## Architecture

### Pourquoi `Wealth` et non `WealthView`

Le dépôt distingue trois familles de contextes :

| Contexte | `Models/` | `Services/` | `Http/` | Suffixe |
| --- | --- | --- | --- | --- |
| `Portfolio`, `RealEstate`, `Market` | oui | parfois | oui | non |
| `Valuation`, `Income` | non | oui | non | non |
| `InstrumentView` | non | non | oui | oui |

`Valuation` et `Income` sont déjà des contextes sans aucune table, qui ne vivent que de ports vers
leurs voisins et portent leurs règles de calcul dans `Services/`. Ni l'un ni l'autre ne porte le
suffixe : celui-ci n'est pas la marque d'un contexte dérivé.

`InstrumentView` le porte parce que le domaine de l'instrument vit dans `Market`
(`Market\Models\Instrument`) : un contexte nommé `Instrument` entrerait en collision avec un nom
déjà pris. Le suffixe désambiguïse, il n'annonce pas un `Instrument` absent.

« Patrimoine » n'entre en collision avec rien. Le contexte s'appelle donc `Wealth`, et prend la
forme de `Valuation` plus le `Http/` que `Portfolio` et `RealEstate` ont aussi parce qu'ils servent
une page — le patrimoine en a une, `/`.

### Nouveau contexte `app/Contexts/Wealth`

```
Wealth/
  WealthProvider.php
  Ports/
    HoldingsPort.php            snapshot titres           → Portfolio
    SecuritiesSeriesPort.php    série titres              → Valuation
    RealEstatePort.php          snapshot + série immo     → RealEstate
    IncomePort.php              dividendes mensualisés    → Income
  Infrastructure/
    PortfolioHoldings.php  ValuationSeries.php
    RealEstateFinancials.php  DividendIncome.php
  Services/
    SeriesAligner.php
  Actions/
    GetWealthOverview.php  BuildWealthSeries.php  GetWealthIncome.php
  Datas/
    WealthOverviewData.php  AssetClassData.php
    WealthSeriesData.php  WealthIncomeData.php
  Http/
    DashboardController.php
```

Un port par contexte source, comme `InstrumentView`. Aucun contexte existant ne gagne de
dépendance : ce sont les ports du nouveau qui tirent.

### `WealthOverviewData`

```php
readonly class WealthOverviewData implements JsonSerializable
{
    public function __construct(
        public float $totalValue,       // titres + patrimoine net immobilier
        public float $totalInvested,
        public float $totalGain,
        public ?float $totalGainPct,    // null quand totalInvested <= 0
        public AssetClassData $securities,
        public AssetClassData $realEstate,
    ) {}
}
```

`AssetClassData` porte `value`, `invested`, `gain`, `?gainPct`. Les deux lignes cliquables du
résumé en sortent, le grand chiffre aussi.

`?float $totalGainPct` est nullable là où `PortfolioOverviewData::$totalGainPct` ne l'est pas : un
prix de revient nul, côté titres, veut dire aucune position. Ici l'investi peut être nul **avec**
du patrimoine — un bien financé à plus de 100 % — et afficher `0 %` mentirait.

### L'investi d'un bien acheté à crédit

```
apport          = prix + frais − capital emprunté       (négatif si financé à plus de 100 %)
cash injecté    = Σ_mois échus  max(0, échéance + charges − loyers)
investi         = apport + cash injecté
patrimoine net  = valeur estimée − capital restant dû
gain            = patrimoine net − investi
```

`apport + capital remboursé` — la formule intuitive — est fausse deux fois. Elle compte le capital
deux fois dès qu'un mois est déficitaire, puisque l'échéance qui sort de la poche **contient** ce
capital. Et elle compte comme sorti le capital remboursé par le locataire, qui n'a rien coûté.

Cas d'école, celui de `propertyFixture(['loan' => true])` : prix 100 000 €, frais 8 000 €, emprunté
80 000 € à taux nul sur 240 mois — donc 333,33 € d'échéance — et un bail à 600 € depuis vingt mois.
L'apport vaut 28 000 €.

Ce fixture exerce les deux branches du `max(0, …)`. Les mois ordinaires sont excédentaires
(600 − 333,33 > 0) et n'injectent rien : le capital y est remboursé par le locataire, et tombe donc
du côté du gain — c'est le levier, et il doit se voir. Le mois de janvier porte les deux charges du
fixture, 750 € de travaux et 250 € de taxe foncière : `600 − 333,33 − 1 000 = −733,33`, il injecte
733,33 €. L'investi de ce bien vaut donc 28 733,33 € et non 28 000 €.

Effet de bord voulu : le cash-flow **positif** n'entre pas dans le gain, il part au bloc Revenus.
Même traitement que les dividendes, qui ne sont pas dans `totalGain` non plus.

### `BuildWealthSeries`

Deux séries construites séparément, alignées ensuite.

- **titres** — `Valuation\Actions\BuildEvolutionSeries($userId, null, Week)`, inchangé.
- **immobilier** — nouvelle `RealEstate\Actions\BuildRealEstateSeries` : pour chaque semaine, la
  somme sur les biens de `valeur estimée − capital restant dû`. Un bien non encore acquis vaut 0.
- **investi** — somme des deux `invested`, sur la même grille.

`Services\SeriesAligner::align()` fait l'union des labels des deux séries et remplit chaque trou par
report de la dernière valeur connue, 0 avant le premier point. Nécessaire : les labels des titres
partent de la première transaction, un bien acheté avant serait tronqué, et sans aucune transaction
il n'y aurait pas un seul label.

La valeur estimée entre deux `PropertyValuation` se lit **en escalier** : la dernière connue à cette
date. Cohérent avec `currentValue = valuations->last()` déjà en place, et honnête — une valeur n'est
connue que le jour où elle a été estimée. Le prix payé est visible : des marches dans l'aire
empilée. L'interpolation linéaire lisserait le tracé mais inventerait des valeurs.

`WealthSeriesData` porte `labels`, `securities[]`, `realEstate[]`, `invested[]`.

### `GetWealthIncome`, et le filtre par source qu'il impose

`Income` agrège déjà **deux** origines : `IncomeSource::Dividend` et `IncomeSource::Rent`, montées
dans `AppServiceProvider` via `IncomeProvider::registers(sources: [...])`. Donc
`IncomeSummaryData::$last12Months` contient déjà les loyers, **bruts**. La formule intuitive
`income.last12Months / 12 + locatif net` compterait les loyers deux fois.

```
dividendes/mois  = GetIncomeSummary($userId, IncomeSource::Dividend)->last12Months / 12
locatif net/mois = Σ (rents12m − expenses12m − loanPayments12m) / 12
total            = dividendes/mois + locatif net/mois
```

Le second existe déjà tel quel dans `GetRealEstateOverview::monthlyCashFlow`.

Le premier impose un **filtre par source** dans `Income`, qui n'existe pas aujourd'hui :
`IncomeSourceRegistry::receiptsFor()` et `::projectedAnnualFor()`, puis `GetIncomeSummary` et
`GetAnnualIncome`, prennent un `?IncomeSource $only = null`. Ajout purement additif, le défaut ne
change aucun appelant.

Ce filtre est de toute façon nécessaire ailleurs : `components/instruments/IncomeSection.vue` vit
désormais sur une page intitulée **Titres**, et y afficher des loyers serait faux. Cette page
demande donc elle aussi le résumé et les barres annuelles filtrés sur `IncomeSource::Dividend`.

`WealthIncomeData` porte `monthlyTotal`, `monthlyDividends`, `monthlyRentalNet`.

### Cache immobilier : empreinte, pas observer

`BuildRealEstateSeries` déroulerait l'échéancier complet de chaque prêt à chaque affichage du
tableau de bord. La part dérivée est pourtant indépendante du temps : l'échéancier ne dépend que de
`(principal, taux, durée, date de début, assurance)`, les mois de loyer attendu que des baux et de
leurs exceptions, les points hebdomadaires que des valuations et de l'échéancier. Elle est donc
matérialisable.

Le signal de péremption est une **empreinte lue dans les données**, jamais un observer. Deux
raisons, les deux dans le dépôt.

`Valuation\Infrastructure\LaravelSeriesCache` a déjà tranché ce point, avec sa raison écrite :

> Volontairement lu dans les données plutôt que posé par un observateur : un import SQL ou une
> migration contourneraient l'observateur, pas les agrégats.

Et `RealEstateDemoSeeder::purgeRelated()` en donne le contre-exemple concret :

```php
PropertyValuation::query()->where('property_id', $property->id)->delete();
Loan::query()->where('property_id', $property->id)->delete();
```

Suppression en masse par le query builder : aucun événement de modèle ne part. Une projection tenue
par observer serait périmée dès le premier `db:seed`, sans une ligne d'erreur pour le dire. Le dépôt
contient déjà des `upsert()` et des `DB::table()->insert()` ailleurs — `EloquentPriceRepository`,
`BackupSeeder` — donc le style d'écriture qui casse les observers y est présent.

`RealEstate\Ports\RealEstateCachePort` + `RealEstate\Infrastructure\LaravelRealEstateCache`, clonés
sur `LaravelSeriesCache`. Empreinte des six tables dont la série dépend, restreinte aux biens de
l'utilisateur :

```
properties           count, max(updated_at), sum(acquisition_price), sum(acquisition_fees)
property_valuations  count, max(updated_at), max(date), sum(value)
loans                count, max(updated_at), sum(principal), sum(annual_rate), sum(term_months)
leases               count, max(updated_at), sum(monthly_rent)
rent_exceptions      count, max(updated_at), sum(amount_override)
property_expenses    count, max(updated_at), sum(amount)
today
```

Les sommes pour la raison déjà commentée dans `LaravelSeriesCache` : `updated_at` ne descend pas
sous la seconde, une correction saisie dans la seconde qui suit la création ne se verrait pas sans
elles.

Le `today` est l'ingrédient que l'empreinte des titres n'a pas besoin de porter. La série des titres
se périme d'elle-même parce que `Price::max('date')` avance ; côté immobilier rien ne bouge tout
seul, alors que l'escalier de valuation et le restant dû se lisent **à aujourd'hui**. Sans la date,
la série de lundi serait encore servie vendredi. Avec elle : un recalcul par jour et par
utilisateur.

**Un cache plutôt qu'une table de projection.** Les deux lecteurs veulent « toutes les lignes d'un
agrégat » — la série complète pour le tableau de bord, l'échéancier d'un bien pour sa fiche. Aucun
ne requête *à travers* les biens. La queryabilité d'une table n'achète donc rien ici, et elle coûte
une migration, un projecteur, une commande de reconstruction et sa colonne d'empreinte. Sur trois
biens, dérouler un échéancier est de l'arithmétique pure.

La table de projection devient le bon choix le jour où il faut interroger en travers — « quels biens
ont un LTV supérieur à 80 % », un classement par rendement, un filtre sur cash-flow. L'empreinte
reste alors le signal, la table remplace seulement le blob.

## Routes et pages

Les URLs restent en anglais comme les existantes ; le français reste dans le texte affiché.

```php
Route::get('/',                 Wealth\Http\DashboardController::class)->name('dashboard');
Route::get('/instruments',      InstrumentView\Http\InstrumentsController::class)->name('instruments.index');
Route::get('/instruments/{id}', InstrumentView\Http\InstrumentDetailController::class)->name('instruments.show');
Route::get('/properties',       RealEstate\Http\PropertiesController::class)->name('properties.index');
Route::get('/properties/{id}',  RealEstate\Http\PropertyDetailController::class)->name('properties.show');
```

`Portfolio\Http\DashboardController` devient `InstrumentView\Http\InstrumentsController` :
déplacement de fichier, corps quasi inchangé, il assemble déjà exactement les props de la page
Titres. Il sort de `Portfolio` parce qu'un contexte de domaine y composait quatre contextes en
HTTP ; `InstrumentView` est le contexte de vue prévu pour ça et détient déjà `GetHoldingTrends` que
cette page consomme. `Portfolio` redevient un contexte de domaine pur.

### Pages Vue

```
Pages/Dashboard.vue           réécrit — trois sections
Pages/Instruments/Index.vue   nouveau — reçoit les six sections actuelles telles quelles
Pages/Instruments/Show.vue    fil d'Ariane à trois crans
Pages/Properties/Index.vue    nouveau
Pages/Properties/Detail.vue   fil d'Ariane à trois crans
```

### Composants

`components/dashboard/` ne décrirait plus ce qu'il contient. Il se scinde :

```
components/dashboard/         trois nouveaux
  WealthSummarySection.vue    grand chiffre, pastille de gain, investi, deux lignes de classe
  WealthEvolutionSection.vue  graphe empilé
  WealthIncomeSection.vue     net mensuel, puis dividendes et locatif net

components/instruments/       git mv depuis dashboard/, corps inchangés
  ValuationSection.vue  EvolutionSection.vue  InstrumentsSection.vue
  PerformancesSection.vue  IncomeSection.vue  SectorsSection.vue

components/properties/
  RealEstateSummarySection.vue  totaux, extrait du haut de RealEstateSection.vue
  PropertyList.vue              la liste des biens, extraite du bas
```

`RealEstateSection.vue` disparaît, découpé en ces deux-là. Les attributs `data-section` et `data-*`
restent identiques : les sélecteurs des tests survivent au déplacement.

Les lignes de classe du résumé sont des `<Link prefetch>` vers `/instruments` et `/properties`. Une
classe à zéro ne montre pas sa ligne ; les deux à zéro donnent l'état vide.

### Fil d'Ariane

`AppBreadcrumb` gère déjà n items.

| Page | Fil |
| --- | --- |
| `/instruments` | Tableau de bord › **Titres** |
| `/instruments/{id}` | Tableau de bord › Titres › **ACME** |
| `/properties` | Tableau de bord › **Immobilier** |
| `/properties/{id}` | Tableau de bord › Immobilier › **Nom du bien** |

### Le graphe empilé casse un invariant, volontairement

`buildValueVsInvestedOption` porte ce commentaire : « seul et même pour le tableau de bord et la
fiche instrument ». Et il ne trace qu'**une** courbe — l'investi n'a plus de tracé depuis un
refacto, il n'apparaît que dans l'infobulle. L'empilement demande donc un second constructeur :

```ts
// lib/chart.ts
export function buildWealthStackOption({ labels, securities, realEstate, invested, ... }): ChartOption
```

Il réutilise `chartFrame`, le `dataZoom` slider et `palette()`. Deux séries `type: 'line'` sur la
même clé `stack`, `areaStyle` plein pour les deux bandes. Infobulle : total, part titres, part
immobilier, investi, gain.

Après ce changement l'application porte deux formes de graphe : empilé sur le tableau de bord,
courbe simple sur `/instruments` et les fiches. C'est le prix du choix « patrimoine total,
empilé ». Le commentaire de `buildValueVsInvestedOption` affirme le contraire et doit être corrigé
plutôt que laissé à mentir.

Il faut une seconde couleur de série. `palette()` recopie à la main les valeurs de `app.css` :
ajouter `--chart-real-estate` là-bas et `realEstate` dans les deux branches de `palette()`. Les
titres gardent `value`, donc leur bande a la même couleur que leur courbe sur `/instruments`.

### Props différées

| Page | Synchrone | Différé |
| --- | --- | --- |
| `/` | `overview` | `series` (groupe `evolution`), `income` (groupe `revenus`) |
| `/instruments` | `overview` | inchangé — les quatre groupes actuels |
| `/properties` | `realEstate` | — |

`overview` du tableau de bord reste synchrone : c'est le grand chiffre, et le différer le ferait
sauter à l'arrivée. C'est aussi ce qui le place dans le document initial, donc dans le cache du
service worker, donc lisible hors-ligne.

## Tests

Convention maison : les tests unitaires vivent à côté du code
(`pest()->…->in('../app/Contexts')`). Seules les pages passent par `tests/Feature` et
`tests/Browser`.

### `app/Contexts/RealEstate/`

| Fichier | Ce qu'il verrouille |
| --- | --- |
| `Actions/BuildRealEstateSeriesTest.php` | escalier de valuation sans interpolation ; 0 avant la date d'acquisition ; restant dû décroissant ; bien sans prêt = valeur estimée ; aucun bien = série vide |
| `Actions/GetRealEstateCashInvestedTest.php` | apport = prix + frais − emprunté ; apport négatif conservé à 110 % ; mois excédentaires n'injectent rien ; vacance locative injecte échéance + charges − loyers ; le capital n'est pas compté deux fois |
| `Infrastructure/LaravelRealEstateCacheTest.php` | cloné sur `LaravelSeriesCacheTest` |

Deux cas de `LaravelRealEstateCacheTest` portent tout l'argument du cache :

```php
it('recalcule après une suppression en masse par le query builder', function () {
    // exactement ce que fait RealEstateDemoSeeder::purgeRelated()
    PropertyValuation::query()->where('property_id', $property->id)->delete();
    // un observer n'aurait rien vu ; l'empreinte, si
});

it('ne resserve pas la série de la veille', function () {
    // Carbon::setTestNow() au lendemain : le `today` de l'empreinte change
});
```

### `app/Contexts/Wealth/`

| Fichier | Ce qu'il verrouille |
| --- | --- |
| `Services/SeriesAlignerTest.php` | union des labels ; report de la dernière valeur connue ; 0 avant le premier point ; une des deux séries vide |
| `Actions/GetWealthOverviewTest.php` | somme des deux classes ; `gainPct` nul quand investi ≤ 0 ; titres seuls ; immobilier seul |
| `Actions/BuildWealthSeriesTest.php` | bien acquis avant la première transaction ⇒ labels étendus ; zéro transaction et un bien ⇒ série non vide |
| `Actions/GetWealthIncomeTest.php` | total = dividendes/12 + locatif net |

`propertyFixture(['loan' => true])` existe déjà dans `tests/Pest.php` — 100 000 € de prix, 8 000 €
de frais, 80 000 € empruntés à taux nul sur 240 mois. C'est le cas d'école de la formule d'investi,
réutilisé tel quel.

### Pages

`tests/Feature/DashboardPageTest.php` fait 15 ko et couvre les sept sections actuelles. Il se
scinde : les titres partent dans `InstrumentsPageTest.php`, l'immobilier dans
`PropertiesPageTest.php`, et `DashboardPageTest.php` se réduit aux props du résumé. Aucune
assertion n'est perdue — c'est un découpage, pas une taille.

Côté navigateur : `tests/Browser/DashboardTest.php` se scinde de même, `BreadcrumbTest.php` gagne
les fils à trois crans, `SmokeTest.php` gagne deux URLs.

### Front

`chart.test.ts` et `chart.dark.test.ts` reçoivent `buildWealthStackOption` : deux séries sur la même
clé `stack`, et la nouvelle couleur présente dans les deux thèmes — le fichier sombre existe
précisément pour ça.

## Découpage des commits

Backend d'abord, page allégée en dernier : l'application ne passe par aucun état cassé.

| # | Message | Contenu |
| --- | --- | --- |
| 1 | `feat: filtre les revenus par origine` | `?IncomeSource $only` dans `IncomeSourceRegistry`, `GetIncomeSummary`, `GetAnnualIncome`, tests |
| 2 | `refactor: extrait le cash-flow mensuel d'un bien en service` | `RealEstate\Services\CashFlowCalculator`, `GetPropertyDetail` refactoré dessus, tests inchangés |
| 3 | `feat: chiffre le cash sorti d'un bien acheté à crédit` | `GetRealEstateCashInvested`, `Support\UserProperties`, tests |
| 4 | `feat: projette la valeur nette immobilière semaine par semaine` | `BuildRealEstateSeries`, `RealEstateSeriesData`, tests |
| 5 | `feat: retient les séries immobilières par empreinte` | `RealEstateCachePort` + `LaravelRealEstateCache`, `RealEstateProvider`, tests |
| 6 | `feat: réunit titres et immobilier en un patrimoine` | contexte `Wealth` entier — ports, adaptateurs, `SeriesAligner`, trois actions, `Datas`, tests |
| 7 | `refactor: sort la page des titres du contexte Portfolio` | contrôleur déplacé vers `InstrumentView`, `Pages/Instruments/Index.vue`, `components/dashboard/` → `components/instruments/`, revenus filtrés sur les dividendes, tests de page scindés |
| 8 | `feat: ouvre une page par classe d'actif` | routes `/instruments` et `/properties`, `Pages/Properties/Index.vue`, `components/properties/`, fils d'Ariane à trois crans |
| 9 | `feat: empile titres et immobilier sur le graphe du patrimoine` | `buildWealthStackOption`, `--chart-real-estate` dans `app.css`, `palette()`, tests des deux thèmes |
| 10 | `feat: pose le résumé du patrimoine en tête du tableau de bord` | `Dashboard.vue` réécrit, `WealthSummarySection`, `WealthEvolutionSection`, `DashboardController` de `Wealth` |
| 11 | `feat: réunit dividendes et loyers nets en un revenu mensuel` | `GetWealthIncome`, `WealthIncomeSection.vue` |
| 12 | `docs: décrit le contexte patrimoine et ses pages` | `docs/architecture.md`, `docs/page-data.md` |

Au commit 4, `Dashboard.vue` pointe déjà sur `components/instruments/` tout en rendant les mêmes
sections ; le commit 7 remplace son corps. Chaque commit reste vert.

Avant chaque commit : `vendor/bin/pint --dirty --format agent`,
`php artisan test --compact --filter=<…>`, et `bun run typecheck` dès que du TypeScript bouge.

## Règles à enregistrer

À la fin du chantier, via `record-rule`, parce qu'elles se re-déduiraient mal :

- sur `app/Contexts/RealEstate/**` — l'investi d'un bien à crédit est `apport + cash injecté`,
  jamais `apport + capital remboursé` : le capital est dans l'échéance, et celui que paie le
  locataire est un gain, pas une mise.
- sur `app/Contexts/Wealth/**` — la péremption se lit dans les données, jamais par observer ;
  `RealEstateDemoSeeder::purgeRelated()` supprime en masse par le query builder.
