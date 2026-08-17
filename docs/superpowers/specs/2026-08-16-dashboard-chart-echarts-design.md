# Reconstruction du graphe d'évolution sur ECharts

Date : 2026-08-16

## Problème

Le graphe d'évolution du tableau de bord est bugué et coûteux à déboguer. Sa
complexité ne vient pas de la donnée mais de son mode de navigation : un
défilement horizontal qui charge l'historique plus ancien à mesure qu'on
atteint le bord gauche.

`EvolutionSection.vue` fait 281 lignes pour tenir ce contrat :

- deux graphes ApexCharts distincts — une colonne d'axe fixe et un graphe
  défilant — dont les zones de tracé doivent coïncider au pixel près, ce qui
  impose de dupliquer leur géométrie dans `lib/chart.ts` ;
- un ancrage manuel du défilement, `anchorUntilStable`, qui repositionne le
  conteneur pendant jusqu'à trente frames parce qu'ApexCharts élargit son SVG
  hors du cycle de Vue ;
- deux `ResizeObserver`, l'un pour la largeur du conteneur, l'autre pour celle
  du contenu ;
- un drag tactile réimplémenté à la main pour supprimer l'inertie du
  navigateur, qui déclenchait des chargements après la fin du geste ;
- un drapeau `scrolledByReader` pour distinguer un geste du lecteur d'un
  repositionnement automatique.

Chacun de ces mécanismes existe pour compenser le précédent. Le couplage
« geste → requête → nouveau rendu → repositionnement » traverse quatre couches
et n'est reproductible qu'en conditions réelles.

## Décisions

Quatre décisions, prises avec le propriétaire du projet, structurent la
reconstruction.

**La fenêtre temporelle se choisit par zoom, pas par défilement.** Un
composant `dataZoom` de type slider affiche une mini-timeline permanente sous
le graphe ; la poignée délimite la fenêtre visible.

**La fenêtre vit entièrement côté client.** Le serveur envoie tout
l'historique quotidien en une fois, le zoom filtre en mémoire. Mesuré sur les
données réelles : 1827 points quotidiens, 6 actifs, 151 Ko de JSON, soit
environ 25 Ko une fois compressé. Ce budget est acceptable pour une prop
différée, et il supprime la totalité de la machinerie de chargement
incrémental.

**ECharts remplace ApexCharts.** Son `dataZoom` couvre le besoin en
configuration pure, sans code de brush maison. La migration couvre les trois
composants qui tracent un graphe, ce qui permet de désinstaller ApexCharts et
de ne garder qu'une seule librairie dans le bundle.

**Le rendu se fait en SVG, pas en canvas.** Les tests navigateur du projet
inspectent le DOM du graphe. Le renderer canvas, qui est le défaut d'ECharts,
rendrait ce style de test impossible et ne laisserait que la capture d'écran.
Le coût du SVG est négligeable ici : six séries produisent six chemins.

## Architecture

### Contrat serveur

`DashboardController` cesse de fenêtrer la série :

```php
'evolutionSeries' => Inertia::defer(fn () => $user !== null
    ? app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Day)
    : EvolutionSeriesData::empty()),
```

Disparaissent : la constante `DEFAULT_EVOLUTION_MONTHS`, la méthode
`evolutionMonths()`, la prop `valuationMonths`, le paramètre de requête
`?months=`.

`EvolutionSeriesData::$hasMore` disparaît également : sans pagination, le
champ ne peut plus valoir que `false`. Sa suppression touche
`ValuationCalculator::evolution()`, qui le calcule, ainsi que
`BuildEvolutionSeries` qui le transmet.

La signature `ValuationCalculator::evolution(?int $months,
ValuationGranularity $granularity)` est conservée. Elle est générique et déjà
couverte par des tests ; seul l'appelant change.

Le type TypeScript devient :

```ts
export interface EvolutionSeries {
    labels: string[];
    perAsset: { assetId: number; name: string; value: number[]; invested: number[] }[];
}
```

### Couche de rendu

Un nouveau composant `resources/js/components/BaseChart.vue` concentre tout le
code impératif du projet en un seul endroit. Il expose une interface
déclarative — une option ECharts et une hauteur — et gère seul le cycle de vie
de l'instance :

```
props    : { option: EChartsOption, height: number }
mounted  : echarts.init(element, null, { renderer: 'svg' })
watch    : option (deep) → setOption(option, { notMerge: true })
watch    : isDark → setOption avec la palette du thème
observe  : ResizeObserver → chart.resize()
unmount  : chart.dispose()
```

Aucun consommateur ne touche à l'instance ECharts. Un composant de section
calcule un objet d'options et le passe en prop ; le DOM en découle
entièrement. C'est la garantie que la classe de bugs actuelle — un état
impératif désynchronisé de l'état Vue — ne peut pas se reformer.

Les imports sont sélectifs pour contenir le poids du bundle :
`echarts/core`, `LineChart`, `GridComponent`, `TooltipComponent`,
`DataZoomComponent`, `SVGRenderer`.

`lib/chart.ts` est réécrit. Disparaissent `buildEvolutionAxis`,
`evolutionGeometry`, `niceAxisMax` et la constante `OVERLAP_PX` : ECharts gère
nativement l'axe des valeurs et le tooltip partagé, qui étaient la raison
d'être de ces fonctions. Le module ne contient plus que des fonctions pures
qui prennent des données et rendent un `EChartsOption` — testables sans
navigateur.

### EvolutionSection

Le composant passe de 281 à environ 70 lignes. Il ne détient plus aucune
référence DOM, aucun `ResizeObserver`, aucun `requestAnimationFrame`, aucun
gestionnaire tactile et aucun appel à `router.reload`.

```
series   : 6 × { type:'line', stack:'total', areaStyle:{}, symbol:'none', sampling:'lttb' }
dataZoom : [ { type:'slider', start:70, end:100, height:40 }, { type:'inside' } ]
tooltip  : { trigger:'axis' } + formatter maison → Valeur, Gain, actifs visibles
```

La fenêtre s'ouvre sur les 30 % les plus récents de l'historique, ce qui
reproduit le comportement attendu aujourd'hui — le graphe montre d'abord la
période récente — sans code d'ancrage.

`hiddenAssetIds`, piloté depuis `HoldingsSection`, continue de filtrer
`perAsset` en amont du calcul des options. Ce contrat entre les deux sections
ne change pas.

### Pages instrument

`PriceHistorySection` trace une ligne, `ValuationSection` en trace deux dont
une en escalier. Les deux passent sur `BaseChart` : la courbe `stepline`
d'ApexCharts devient `step: 'end'` en ECharts.

`ChartRangeToggle` et le rechargement serveur par période restent en place sur
la page instrument. Ce mécanisme, déclenché par un clic explicite et non par
un geste continu, n'a jamais posé de problème et sort du périmètre.

ApexCharts est ensuite désinstallé : `bun remove apexcharts vue3-apexcharts`.

## Tests

### Suppression

`tests/Browser/DashboardEvolutionScrollTest.php` est supprimé. Ses six tests
portent sur le scroller horizontal, l'ouverture sur le point le plus récent,
la visibilité de l'axe fixe pendant le défilement, le chargement au bord
gauche et l'alignement des deux graphes ApexCharts. Aucun de ces comportements
n'existe dans le nouveau design ; le fichier ne peut pas être adapté, seulement
remplacé.

Suppression approuvée explicitement par le propriétaire du projet le
2026-08-16.

### Réécriture

Ces tests gardent leur intention et changent de sélecteur, le DOM ApexCharts
étant remplacé par le DOM ECharts :

| Fichier | Portée |
| --- | --- |
| `DashboardEvolutionLayoutTest` | 2 tests — titre sur sa propre ligne, marges du graphe |
| `DashboardChartLegendTest` | 1 test — nombre de graphes rendus, absence de camembert |
| `SystemThemeTest` | 1 test sur 4 — la palette des aires suit le thème |
| `InstrumentValuationChartTest` | 3 tests — séries tracées, axe en euros, pas de légende |

### Nouveaux tests

`tests/Browser/DashboardEvolutionZoomTest.php` couvre le nouveau mode de
navigation : le slider est présent, déplacer sa poignée restreint la fenêtre
affichée, et aucune requête réseau n'est émise pendant le geste — cette
dernière assertion verrouille la décision « la fenêtre vit côté client ».

Des tests unitaires portent sur les constructeurs d'options de `lib/chart.ts`.
Ces fonctions étant pures, ils s'exécutent sans navigateur : une entrée de
données, une assertion sur l'objet d'options produit.

`DashboardPageTest` perd ses deux tests de fenêtrage — « windows the dashboard
evolution series to six months by default » et « extends the dashboard
evolution series when more months are requested » — remplacés par un test qui
vérifie que la réponse porte l'historique complet et ne contient plus de prop
`valuationMonths`.

`EvolutionSeriesDataTest` et `ValuationCalculatorTest` perdent leurs
assertions sur `hasMore`.

## Écarts constatés à la mise en œuvre

Cinq points ont évolué entre le design et le code livré.

**L'axe des abscisses est temporel, pas catégoriel.** Un axe de catégories
graduait par pas fixe et répétait le même libellé plusieurs fois de suite
(« avr. 26, avr. 26, mai 26… »). L'axe `time` laisse ECharts choisir la
granularité selon l'amplitude réellement visible. Les noms de mois viennent du
paquet de langue `langFR`, enregistré au chargement et passé à chaque
instance.

**Les graphes portent une description accessible.** Le rendu SVG d'ECharts ne
nomme pas ses séries dans le DOM, ce qui privait les tests de tout point
d'accroche sémantique. Chaque constructeur d'options rédige une phrase
française posée en `aria-label` sur le conteneur. Les tests s'y appuient, et
un lecteur d'écran y gagne réellement.

**La fenêtre de zoom est publiée en attribut.** `BaseChart` expose
`data-zoom-window="70-100"` et émet un événement `zoom`. La section mémorise
la fenêtre dans une variable délibérément non réactive : la réintroduire dans
les options recalculerait et repeindrait le graphe à chaque pixel de
glissement. Cette mémorisation permet à la fenêtre de survivre au masquage
d'un titre depuis la liste des positions.

**Le graphe du tableau de bord a rejoint le style de la fiche instrument.** Les
six aires empilées par actif du bloc `series` ci-dessus ont d'abord cédé la
place à deux courbes, valeur contre investi, comme sur la fiche instrument.
L'alignement a ensuite été mené jusqu'au bout : plus d'aire sous la courbe
Valeur, graduations automatiques sur l'axe des valeurs — la variante « bornes
exactes seules » a vécu deux commits — hauteur 300 comme la fiche instrument, et
une seule infobulle pour les deux pages, celle qui détaille Valeur, Investi et
Gain/Perte. `lib/chart.ts` porte désormais `valueVsInvestedSeries` et
`valueVsInvestedTooltip`, partagées par `buildEvolutionOption` et
`buildValuationOption`. Le tableau de réécriture ci-dessus cite « la palette des
aires suit le thème » pour `SystemThemeTest` : ce test porte en réalité sur la
teinte des libellés d'axe, et il n'y a plus d'aire sur ce graphe.

**Les tests unitaires sur `lib/chart.ts` n'ont pas été écrits.** Le projet n'a
pas de lanceur de tests JavaScript, et en ajouter un serait un changement de
dépendance à décider séparément. Les constructeurs d'options sont couverts par
les tests navigateur, conformément à la convention du dépôt.

Mesures relevées après migration : le dossier `public/build/assets` passe de
1512 à 920 Ko. Sur cinq ans d'historique quotidien pour six actifs, dix zooms
molette consécutifs prennent 25 ms et le masquage d'un titre est
imperceptible.

## Hors périmètre

- Le rechargement par période de la page instrument, qui fonctionne.
- Le graphe radar du catalogue d'instruments, qui n'utilise pas ApexCharts.
- Toute évolution du calcul de la série côté back : `ValuationCalculator`
  n'est touché que pour retirer `hasMore`.
