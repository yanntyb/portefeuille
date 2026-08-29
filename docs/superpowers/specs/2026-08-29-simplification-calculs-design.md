# Simplification des calculs — conception

Date : 2026-08-29
Statut : conception validée, plan d'implémentation à écrire

## Pourquoi

Un balayage des 80 fichiers PHP non-test a montré deux choses.

D'abord, cinq règles de calcul sont écrites à plusieurs endroits qui ne se voient pas les uns les
autres, et deux d'entre elles ont divergé silencieusement. Ensuite, une dizaine d'actions mêlent
la lecture (Eloquent, ports) au calcul, si bien que vérifier une division demande de monter des
factories. Le projet a pourtant déjà le remède chez lui : `RealEstate` sépare `Support/`
(traduction), `Services/` (calcul pur) et `Actions/` (orchestration), et `ValuationCalculator` se
teste sur des littéraux.

Ce chantier généralise ce qui marche déjà, sans changer une seule sortie — à une exception près,
documentée plus bas.

## Constats

### Les duplications

**Gain et gain en pourcentage — 4 sites, 2 désaccords.**

- `Portfolio/Actions/GetPortfolioOverview.php:71-74` — dérivation par ligne
- `MarketView/Actions/GetInstrumentDetail.php:56-59` — même formule au caractère près, garde
  `cost > 0.0` comprise, dans un autre contexte
- `Wealth/Datas/AssetClassData.php:44-52` — troisième écriture, sur `value - invested` au lieu de
  `marketValue - cost` : sujet voisin, pas identique
- `Portfolio/Actions/GetPortfolioOverview.php:117` — le total, qui rend **`0.0`** là où les trois
  autres rendent `null`

Un portefeuille dont aucune ligne n'a de coût connu affiche donc « 0 % » en total et « — » par
ligne. `AssetClassData` documente précisément pourquoi `0 %` mentirait ; le total du portefeuille
ne suit pas son propre projet.

**Prix de revient moyen — 4 sites, 2 formes.**

- `Portfolio/Actions/ProjectHolding.php:25-26` et `Portfolio/Actions/CalculateRealizedGain.php:26-28`
  — `buyCost / buyQty` sur un flux de transactions
- `MarketView/Infrastructure/PortfolioHoldings.php:35-50` et
  `Income/Sources/Dividend/Infrastructure/PortfolioPositionHistory.php:80-86` — moyenne pondérée
  des enveloppes, **même code à la Data de sortie près**, dans deux contextes différents, tous
  deux requêtant `Portfolio\Models\Holding` en direct

La cause n'est pas la négligence : `Portfolio` n'expose pas la position par actif, alors les deux
voisins l'ont réimplémentée.

**Fenêtre « 12 mois » — 5 sites, 2 définitions.**

- Mois pleins (`startOfMonth()->subMonthsNoOverflow(11)`) :
  `RealEstate/Support/PropertyFinancialsAssembler.php:31`,
  `RealEstate/Actions/GetRealEstateIncome.php:38`,
  `RealEstate/Actions/GetPropertyDetail.php:76`
- Glissante au jour (`subYear()->startOfDay()`) : `Income/Actions/GetIncomeSummary.php:31`,
  `Income/Sources/Dividend/Actions/GetAssetDividendHistory.php:44`

**Escalier « dernière valeur de date ≤ X » — 2 sites.**
`RealEstate/Actions/BuildRealEstateSeries.php` (`valueAt`) et
`Valuation/Services/ValuationCalculator.php` (`valueAtDate`).

**Accumulation de séries index par index puis `round(…, 2)` — 3 sites.**
`Wealth/Infrastructure/PortfolioAssetClass.php` (`sum`), `Wealth/Actions/BuildWealthSeries.php`,
`RealEstate/Actions/BuildRealEstateSeries.php`. `Wealth\Services\SeriesAligner` n'en couvre
qu'une partie.

### La divergence sémantique

`Portfolio` compte une ligne **par enveloppe** : `GetPortfolioOverview` ne groupe pas par
`asset_id`, donc un titre tenu dans deux comptes donne deux lignes. Les adaptateurs de MarketView
et d'Income agrègent au contraire **par actif**.

Les deux sont légitimes et servent des questions différentes. Aucune n'est nommée comme un choix.

### Le calcul cousu à la lecture

Marqueur commun : le test doit monter des factories pour vérifier une division.

| Fichier | Ce qui est pur | Signal |
| --- | --- | --- |
| `Portfolio/Actions/GetSectorBreakdown.php` | ~45 des 98 lignes : normalisation des poids, répartition, repli `Other`, tri, pourcentages | Son test monte `Wallet` + `Instrument` + `Price` + `Holding` + `SectorAllocation` |
| `RealEstate/Actions/BuildRealEstateSeries.php` | `weeklyLabels`, `valueAt`, `injectedUpTo`, `remainingAt` — ~90 des 174 lignes | Aucun test unitaire possible |
| `Portfolio/Actions/GetPortfolioOverview.php` | Dérivation par ligne, `summarize()` | Source des deux désaccords sur `gainPct` |
| `RealEstate/Actions/GetPropertyDetail.php` | `expenseYears`, `byCategory`, `loanSummary`, `cashFlowWindowStart` — ~100 des 170 lignes | `loanSummary` agrège des `AmortizationLineData` : travail de `LoanAmortizationCalculator` |
| `RealEstate/Support/PropertyFinancialsAssembler.php` | Les trois agrégations fenêtrées de `financialsFor` | `Support/` doit traduire, pas calculer |
| `RealEstate/Actions/GetRealEstateIncome.php` | Regroupement par année, fenêtre | |
| `Income/Sources/Dividend/Actions/GetAssetDividendHistory.php` | Totaux, `yieldOnCost` | |
| `Income/Actions/GetIncomeSummary.php`, `GetAnnualIncome.php` | Agrégations | Petits, déjà lisibles |
| `MarketView/Actions/GetHoldingTrends.php` | `changePct`, `downsample` | Petit |

### Ce qui est déjà bon

À ne pas toucher : tout `Market` (I/O pur, aucun métier) ; `MarketView/Infrastructure` (remappage
pur, conforme à sa règle) ; les six `Services` existants ; le triptyque de `RealEstate`, qui est
le modèle.

## Doctrine

**Règle A — un calcul a un propriétaire, les autres demandent.**
Le remède aux duplications inter-contextes n'est pas un calculateur partagé dans un dossier
neutre : c'est que le contexte propriétaire calcule et que les autres cessent de recalculer.
`Portfolio` est propriétaire du gain et de la position. `MarketView/GetInstrumentDetail` lira sa
position au lieu de la dériver — MarketView ne calcule rien, c'est déjà sa règle écrite, et
`buildPosition()` y contrevient. Aucun nouveau dossier de base n'est créé.

**Règle B — `Services/` calcule, `Actions/` orchestre, `Support/` traduit.**
Un `Services/` ne connaît ni Eloquent, ni port, ni conteneur : entrées nues ou Datas de son propre
contexte, sortie idem, test co-localisé construisant l'objet avec `new`. Une `Action` lit, appelle,
emballe. `Support/` traduit Eloquent → Data et rien d'autre.

**Règle C — deux calculs qui divergent ne fusionnent pas, ils se nomment.**
Les deux fenêtres « 12 mois » et les deux notions de position servent chacune un endroit où elle a
raison. Le travail est de leur donner un nom explicite et un site unique de définition, pas d'en
écraser une. Seul le désaccord `null` vs `0.0` sur `gainPct` est un bug, et se tranche vers `null`.

## Les unités

### Portfolio

- `Services/HoldingValuator` — dérivation d'une ligne (`marketValue`, `cost`, `gain`, `gainPct`)
  et totalisage. Seul site de la formule ; `gainPct` rend `null` sur coût nul, au total comme à la
  ligne.
- `Services/PositionAggregator` — les enveloppes d'un actif ramenées à une position, moyenne
  pondérée comprise.
- `Services/CostBasis` — prix de revient d'un flux de transactions.
- `Services/SectorSplitter` — répartition sectorielle : prend `array<int, float> $valueByAsset` et
  les poids par actif, rend les parts.
- `Actions/GetPortfolioPositions` — nouvelle lecture exposée : la position par actif.

`GetPortfolioOverview`, `GetSectorBreakdown`, `ProjectHolding` et `CalculateRealizedGain` se
réduisent à lire, appeler, emballer.

### MarketView

- `GetInstrumentDetail::buildPosition()` disparaît ; la position vient de Portfolio.
- `Services/SparklineReducer` — `changePct` et `downsample`, extraits de `GetHoldingTrends`.
- `Infrastructure/PortfolioHoldings` perd sa requête et son `aggregate()` : il remappe
  `GetPortfolioPositions`.

### Income

- `Services/ReceiptTotals` — les agrégations de `GetIncomeSummary`, `GetAnnualIncome` et
  `GetAssetDividendHistory`, qui font le même travail sur les mêmes reçus.
- `Sources/Dividend/Infrastructure/PortfolioPositionHistory` perd sa requête `Holding` et son
  `aggregate()` : il remappe `GetPortfolioPositions`.

### RealEstate

- `Services/SeriesStepper` — l'escalier `valueAt` et les labels hebdomadaires.
- `Services/LoanAmortizationCalculator` récupère `loanSummary()` de `GetPropertyDetail`.
- `Services/ExpenseGrouper` — `expenseYears` et `byCategory`.
- `Services/PropertyWindowTotals` — les trois agrégations fenêtrées de `financialsFor`.
- `Support/PropertyFinancialsAssembler` redevient un pur traducteur.

### Le temps, transverse

Les deux définitions ne se croisent jamais : les trois sites de `RealEstate` veulent tous les mois
pleins, les deux sites d'`Income` veulent tous la fenêtre glissante au jour. Aucune unité partagée
n'est donc nécessaire, et aucune dépendance entre contextes n'est créée.

- `RealEstate/Services/RollingWindow::monthsFull(Carbon $today): string` — début de la fenêtre,
  pour `PropertyFinancialsAssembler`, `GetRealEstateIncome`, `GetPropertyDetail`
- `Income/Services/RollingWindow::slidingDays(Carbon $today): Carbon` — pour `GetIncomeSummary` et
  `GetAssetDividendHistory`

Deux classes de même nom dans deux contextes, chacune portant la seule fenêtre que son contexte
emploie. Aucun appelant ne change de fenêtre ; chacun cesse simplement de la redéfinir.

## L'invariant

Ce chantier ne change **aucune sortie**. `Shared/Pwa/Http/SnapshotController.php:45` publie
`sha1(json_encode($body))` sur un corps qui réunit les quatre contextes — `dashboard` (Wealth),
`classes` et `assets` (MarketView), `properties` (RealEstate, fiches et échéanciers compris). Un
hash identique avant et après prouve que les six pages rendent le même JSON, ordre des clés
compris. C'est le filet du refactor entier, et il attrape aussi le piège des Datas jumelles de
MarketView.

**Seule exception assumée** : `gainPct` passe de `0.0` à `null` sur un total à coût nul (lot 1).
Le hash change une fois, sur ce cas, et c'est la correction du bug.

## Les lots

Neuf lots, chacun committable et vert seul. Chaque lot : `vendor/bin/pint --dirty --format agent`,
tests ciblés, commit.

**Lot 0 — le filet.** Un test qui seede un jeu couvrant les quatre expositions plus un bien,
construit l'instantané et fige son `sha1`. Doit inclure un actif tenu dans deux enveloppes, sinon
la moyenne pondérée n'est pas sous le filet. Aucune modification de code applicatif.

Le temps doit être gelé (`Carbon::setTestNow()`) et les dates du jeu posées en relatif de cet
instant : presque tout le code lit `Carbon::now()` — fenêtres glissantes, échéanciers, projections
— et un hash figé sur l'horloge réelle casserait dès le lendemain. Si la sérialisation de flottants
rend le `sha1` instable d'une plateforme à l'autre, replier le filet sur une comparaison de tableau
normalisé plutôt que d'affaiblir le jeu de données.

**Lot 1 — `HoldingValuator`.** Extraction ; `GetPortfolioOverview` réduite. Porte l'unique
changement de comportement voulu (`gainPct` à `null`). Le hash du lot 0 est mis à jour ici, avec
la raison en commentaire.
*Prouve* : mêmes chiffres sur les cas non dégénérés, `null` sur le cas dégénéré.

**Lot 2 — `PositionAggregator` + `GetPortfolioPositions`.** `MarketView/PortfolioHoldings` et
`Income/PortfolioPositionHistory` deviennent du remappage.
*Prouve* : hash inchangé ; aucun des deux adaptateurs ne mentionne plus `Holding::query()`.

**Lot 3 — `CostBasis`.** `ProjectHolding` et `CalculateRealizedGain` partagent leur division.
*Prouve* : hash inchangé, gains réalisés identiques.

**Lot 4 — `SectorSplitter`.** `GetSectorBreakdown` vidée de sa math.
*Prouve* : son test perd ses factories pour les cas de calcul — poids nuls, actif sur trois
secteurs, somme des poids ≠ 1 — et n'en garde que pour le chemin de lecture.

**Lot 5 — MarketView.** `GetInstrumentDetail` demande sa position ; `SparklineReducer` sort de
`GetHoldingTrends`. Dépend des lots 1 et 2.
*Prouve* : hash inchangé — le lot où le filet compte le plus, puisqu'il touche toutes les fiches.

**Lot 6 — RealEstate.** `SeriesStepper`, `loanSummary()` rendu à `LoanAmortizationCalculator`,
`ExpenseGrouper`, `PropertyWindowTotals`, `PropertyFinancialsAssembler` réduit. Le plus gros lot :
à découper en trois commits si `BuildRealEstateSeries` et `GetPropertyDetail` résistent.

Le filet le couvre entièrement, contrairement à ce qu'un coup d'œil à
`tests/Feature/PropertiesPageTest.php` (400 octets) laisse croire :
`BuildRealEstateSnapshot` porte la fiche complète en `byId.{id}.property` via `GetPropertyDetail`,
et son échéancier en `byId.{id}.amortization`. Le lot 0 suffit, à condition que son jeu comprenne
un bien **avec prêt** — `propertyFixture(['loan' => true])` — sans quoi `loanSummary()` et
l'échéancier restent hors du hash.

**Lot 7 — `ReceiptTotals`.** Les trois agrégations de revenus sur un site.

**Lot 8 — `RollingWindow`.** Les cinq sites nommés explicitement, aucun ne changeant de fenêtre.
Touche Income et RealEstate, donc après leur stabilisation.

**Lot 9 — `SeriesAligner::accumulate()`.** `PortfolioAssetClass::sum()` et la boucle d'apports de
`BuildWealthSeries` empilent toutes deux des séries index par index avant d'arrondir ; les deux
vivent dans `Wealth`, dont `SeriesAligner` est déjà le calculateur de séries.

## Hors périmètre

- **Découper `Valuation/Services/ValuationCalculator`** (434 lignes, sept responsabilités). Il est
  pur et bien testé : son problème est le découpage, pas la couture. Chantier séparé.
- **Unifier les deux fenêtres « 12 mois »** ou **les deux notions de position**. Règle C : elles
  sont nommées, pas fusionnées.
- **L'accumulateur de `BuildRealEstateSeries`.** Il empile scalaire par scalaire dans une boucle
  imbriquée sur les biens, pas série sur série : sa forme diffère de celle du lot 9, et l'y faire
  entrer imposerait une dépendance de `RealEstate` vers `Wealth`. Le troisième site reste.
- **L'escalier de `ValuationCalculator::valueAtDate()`**, jumeau de celui du lot 6 : il vit dans le
  calculateur laissé hors périmètre, et l'en extraire créerait une dépendance de `Valuation` vers
  `RealEstate`. Les deux escaliers restent.
- **Les pages d'analyse** (concentration, contribution à la performance, drawdown, par classe
  d'actif, avec lien en bas de listing et entrée `analysis` dans le blob). Elles ont motivé ce
  balayage et dépendent de ses lots 1 et 2 — notamment du choix ligne-vs-actif, qu'une
  concentration doit trancher pour ne pas sous-estimer un titre à cheval sur deux enveloppes.
  Spec séparée, après ce chantier.
