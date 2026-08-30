# Pages d'analyse par exposition — conception

Date : 2026-08-30
Statut : conception validée, plan d'implémentation à écrire
Dépend de : `docs/superpowers/specs/2026-08-29-simplification-calculs-design.md` (livré)

## Pourquoi

La page liste d'une exposition empile six sections de nature différente : trois décrivent ce que
le portefeuille contient (valorisation, évolution, instruments), trois l'analysent (performances,
secteurs, revenus). Les secondes n'ont pas leur place dans une liste, et l'endroit leur manque
pour grandir.

Ce chantier les isole dans une page d'analyse par exposition, et en profite pour y ajouter les
trois analyses qui manquaient le plus : concentration, contribution à la performance, drawdown.

Il s'appuie sur le chantier de simplification qui vient d'être livré : `HoldingValuator`,
`GetPortfolioPositions` et la doctrine `Services/` en sont les fondations directes.

## Décisions

### Une page d'analyse par exposition

`/actions/analyse`, `/crypto/analyse`, `/obligations/analyse`, `/matieres-premieres/analyse` —
une par cas de `AssetClass::cases()`, engendrée depuis l'enum comme les pages liste.

Pas de page d'analyse transverse à tout le portefeuille : le tableau de bord tient déjà ce rôle,
et une analyse qui mêle actions et crypto dirait peu de chose.

### Position, et non ligne

`Portfolio` expose deux notions, distinctes et nommées :

- `HoldingLineData` — une ligne par enveloppe ; un titre sur un PEA et un CTO en donne deux.
- `PositionLineData` — une position par actif, enveloppes confondues, moyenne pondérée comprise.

**Les analyses par actif lisent les positions.** L'argument est décisif sur la concentration :
mesurée sur les lignes, elle sous-estime exactement ce qu'elle sert à détecter. Un titre à 30 % du
portefeuille réparti sur deux comptes apparaîtrait comme deux lignes à 15 %, et le HHI le ferait
passer pour une exposition modérée. L'enveloppe est un fait fiscal, pas un fait de marché : le
risque ne se diversifie pas en ouvrant un second compte.

La contribution à la performance suit, pour une raison plus faible : éviter qu'un même titre
occupe deux rangs d'un classement de contributeurs.

Le drawdown ne lit ni l'un ni l'autre — il se calcule sur une série de valeurs dans le temps.

**Conséquence assumée** : la page liste affiche des lignes, la page analyse raisonne en positions.
Un même titre y apparaît une fois d'un côté, deux de l'autre. C'est cohérent — chaque page pose
une question différente — mais ça surprend, donc le rendu doit le dire (voir « Vocabulaire »).

### Le calcul vit chez son propriétaire

Règle A du chantier précédent, désormais écrite dans `.ai/rules/services.md` : le contexte
propriétaire calcule, `MarketView` demande par ses ports.

- `Portfolio\Services\Concentration` et `Portfolio\Services\PerformanceContribution`
- `Valuation\Services\Drawdown`

Chacun est un `Services/` au sens de la règle : ni Eloquent, ni port, ni conteneur ; entrées nues,
sortie nue ; test co-localisé construisant l'objet avec `new`, sans base.

## Les trois analyses

### Concentration

```php
namespace App\Contexts\Portfolio\Services;

final class Concentration
{
    /** @param  list<float>  $values  Valeurs de marché des positions, ordre indifférent. */
    public function of(array $values): ConcentrationData
}
```

Rend le poids du top 1, du top 3, du top 5, et l'indice de Herfindahl-Hirschman.

Le service prend des **valeurs**, pas des poids : il normalise lui-même, pour que l'appelant n'ait
pas à diviser avant et que la règle de normalisation vive à un seul endroit.

Cas à couvrir :
- portefeuille vide → HHI et poids à `null`, jamais à `0.0` : un portefeuille sans position n'a
  pas une concentration de zéro, il n'en a pas. Même raisonnement que le `gainPct` du chantier
  précédent, et même piège à éviter ;
- position unique → HHI à 1, top 1 et top 3 à 100 % ;
- moins de trois positions → le top 3 vaut 100 %, ce n'est pas une erreur ;
- **positions sans cours connu (`marketValue` nul) exclues**, non comptées à zéro : les inclure
  diluerait artificiellement la concentration mesurée.

### Contribution à la performance

```php
namespace App\Contexts\Portfolio\Services;

final class PerformanceContribution
{
    /**
     * @param  list<array{assetId: int, gain: ?float, marketValue: ?float}>  $positions
     * @return list<ContributionData>
     */
    public function of(array $positions, float $totalValue): array
}
```

Une ligne par position : sa **contribution**, soit son gain rapporté à la valeur totale du
portefeuille, et son **poids**, soit sa valeur de marché rapportée à cette même valeur totale.

**Le dénominateur est la valeur totale, jamais le gain total.** Rapporter au gain total —
« quelle part du gain vient de cette ligne » — paraît plus direct mais s'effondre dès que le
portefeuille perd : le dénominateur devient négatif, et une position gagnante affiche alors une
contribution négative. Rapporté à la valeur, le chiffre garde son sens dans les deux cas et se lit
en points de performance du portefeuille : une position qui contribue +3 points en a apporté trois
au rendement d'ensemble, que celui-ci soit positif ou non.

C'est la distinction qui fait tout le sujet — une position à +80 % sur 2 % du portefeuille
contribue moins qu'une à +12 % sur 30 %, ce que le pourcentage de gain seul ne dit pas.

Cas à couvrir :
- positions sans gain connu exclues de la liste, non affichées à zéro ;
- valeur totale nulle → contributions et poids à `null`, jamais `0.0`, pour la raison déjà
  donnée sur la concentration ;
- portefeuille en perte → contributions négatives, ce qui est correct et lisible ;
- ordre de la liste : contribution décroissante, pour que les contributeurs sortent en tête.

### Drawdown

```php
namespace App\Contexts\Valuation\Services;

final class Drawdown
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>   $values
     */
    public function of(array $labels, array $values): DrawdownData
}
```

Rend le drawdown maximum — profondeur, date du plus-haut, date du creux — et le drawdown courant.
Une seule passe sur la série.

Cas à couvrir :
- série vide → drawdown nul, dates nulles ;
- série monotone croissante → drawdown nul, aucune date ;
- plus-haut atteint au dernier point → drawdown courant nul ;
- série entièrement décroissante → le plus-haut est le premier point.

**La série totale de l'exposition, et d'où elle vient.** Le drawdown a besoin de la valeur totale
de l'exposition dans le temps, pas des séries par actif que `ValuationPort::evolutionFor()` rend
aujourd'hui.

Deux chemins étaient possibles. Sommer les séries par actif côté appelant serait une **quatrième**
occurrence de l'idiome « accumuler index par index » que le chantier précédent vient de réduire à
deux — écarté pour cette raison.

Retenu : une action `Valuation\Actions\BuildExposureSeries` qui refait ce que
`BuildPortfolioPerformances::build()` fait avant ses fenêtres — filtrer les transactions par
`InstrumentDirectoryPort::idsOfClasses()`, appeler `ValuationCalculator::calculateDaily()` — et
rend le `ValuationSeriesData` dont `valuations` **est** la série totale. Elle réutilise un chemin
existant et n'ajoute aucune duplication.

Attention au nom de cache : comme pour les performances, le filtre par exposition doit entrer dans
le nom retenu. Une fenêtre glissante agrège les transactions avant d'en tirer son résultat, elle
ne se découpe pas après coup — un nom réutilisé servirait le résultat d'une classe à l'autre.

## Composition des pages

### Répartition des sections

| Page | Sections |
| --- | --- |
| Listing (`/actions`) | Valorisation, Évolution, Instruments |
| Analyse (`/actions/analyse`) | Concentration, Contribution, Drawdown, Performances, Secteurs, Revenus |

`SectorsSection`, `PerformancesSection` et `IncomeSection` déménagent telles quelles : ce chantier
ne les réécrit pas.

Les gates existants suivent leur section — `hasSectors` et `hasIncome` restent portés par la prop
`assetClass`. La page analyse de la crypto ne montre donc que les trois nouvelles analyses, ce qui
est précisément pourquoi on a retenu trois analyses qui fonctionnent sur toutes les expositions.

### Routes

Même engendrement depuis l'enum que les pages liste, dans la même boucle de `routes/web.php` :

```php
Route::get("{$assetClass->slug()}/analyse", AssetClassAnalysisController::class)
    ->defaults('exposure', $assetClass->value)
    ->name("classes.{$assetClass->value}.analyse");
```

Ajouter une classe d'actif continue de n'être jamais ajouter une route à la main.

Un second contrôleur plutôt qu'un paramètre sur `AssetClassController` : les deux pages ne
partagent aucune prop, et les mêler ferait une composition à trous.

### Props différées

La page analyse n'a **aucune prop synchrone**, à la différence du listing dont `overview` l'est.

- `performances`, `secteurs`, `revenus` — les groupes existants suivent leurs sections.
- `analyses` — un seul groupe pour les trois neuves : elles viennent des mêmes lectures mémoïsées
  (`GetPortfolioPositions` est liée en `scoped`), les séparer ferait trois attentes pour un seul
  travail.

### Ports

`PortfolioOverviewPort` gagne les deux analyses par actif, `ValuationPort` gagne le drawdown. Un
port par contexte voisin, pas par action — la règle de `MarketView` est inchangée.

Les Datas de sortie (`ConcentrationData`, `ContributionData`, `DrawdownData` et leurs jumelles
`MarketView`) sont neuves : elles n'ont pas d'original chez le voisin, donc pas de contrainte de
JSON à l'octet près à tenir, seulement la discipline habituelle de jumelage.

## Hors-ligne

`BuildMarketViewSnapshot` porte une clé `analysis` par classe, et **perd** `sectorBreakdown`,
`income` et `annualIncome` de sa composition liste, puisqu'elles déménagent.

Deux conséquences assumées :

1. **Le hash de l'instantané change, une fois.** Tous les clients retéléchargent leur blob à la
   prochaine occasion. C'est voulu. `tests/Feature/SnapshotInvariantTest.php` doit être mis à jour
   avec la raison écrite à côté de la constante, comme cela a été fait pour `gainPct`. Le jeu de
   données du filet doit aussi porter de quoi remplir la page analyse — sans quoi on refigerait un
   hash sur des sections vides, l'erreur que la revue du chantier précédent a relevée sur les
   charges d'un bien.
2. **Le store front gagne un accesseur.** `useSnapshotStore` expose `classList(key)` ; il lui faut
   `classAnalysis(key)`. `swCache.ts` sert déjà les pages jamais visitées depuis le blob, donc
   `/actions/analyse` sera lisible hors ligne sans y être passé — c'est l'intérêt.

Rappel du piège documenté : une réponse partielle synthétisée ne doit jamais porter
`deferredProps`, sous peine de boucle infinie de rendu.

## Navigation et vocabulaire

**Le lien.** En bas du listing, sous la dernière section, vers `/{slug}/analyse`. Une seule entrée,
pas d'onglets : le listing reste la page d'arrivée d'une exposition.

**Le fil.** Sur la page analyse, `AppBottomBar` porte `Tableau de bord › Actions › Analyse`, sur le
motif existant. Pas de bouton retour : le fil le fait.

**Le vocabulaire.** Conséquence directe du choix position-plutôt-que-ligne : le listing dit
« lignes », l'analyse dit « positions ». La section de concentration s'intitule « Concentration des
positions », et lorsqu'un titre est à cheval sur deux enveloppes, le rendu indique qu'il s'agit
d'un regroupement. Sans cela, un lecteur qui compare les deux pages conclura à un bug.

Tout texte visible reste en français.

## Hors périmètre

- **Une allocation cible** et la sur/sous-pondération sectorielle qu'elle permettrait : cela
  suppose de stocker une cible, donc une table et une interface de saisie. C'est un chantier à
  part, et le seul des candidats écartés qui élargisse vraiment le périmètre fonctionnel.
- **Le rendement sur prix de revient** : il ne concerne que les expositions à revenus, donc pas la
  crypto. Retenu comme candidat, écarté de la v1 au profit des trois qui marchent partout.
- **La performance dans le temps rapportée aux apports**, le calendrier de revenus projeté, les
  corrélations : intéressants, non retenus.
- **Les deux consolidations que la revue du chantier précédent a signalées** et qui touchent ces
  zones : `GetPortfolioPositions` est entièrement dérivable de `GetPortfolioOverview` (on passerait
  de trois lecteurs de `holdings_projection` à deux), et `SeriesStepper::sumUpTo()` fait le même
  filtre-et-somme que `PropertyWindowTotals::within()` sous un autre nom. Elles restent des
  chantiers de conception, pas des correctifs — mais ce chantier-ci rouvre `GetPortfolioPositions`,
  donc c'est l'occasion de trancher la première si on veut la faire.
- **`PositionLineData::$lastPrice`** n'a aujourd'hui aucun appelant en production. Les analyses
  n'en ont pas besoin non plus. Si ce chantier ne lui en donne pas un, il faut le retirer plutôt
  que de le laisser grossir la Data.
