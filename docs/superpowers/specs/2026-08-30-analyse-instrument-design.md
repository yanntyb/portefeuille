# Section « Analyse » de la fiche instrument

Date : 2026-08-30
Statut : validé, prêt pour le plan d'implémentation

## Intention

La fiche d'un actif (`/asset/{id}`) montre aujourd'hui sa valeur, sa courbe, ses transactions, ses
performances, ses dividendes et ses secteurs. Elle ne dit rien de ce qui décide d'un renfort : où
se situe le cours dans sa tendance, s'il est en excès, ce que la ligne fait bouger dans le
portefeuille.

La section **Analyse** répond à deux questions, et à elles seules :

1. **Renforcer ou alléger ?** — le timing, à un horizon de renforts mensuels.
2. **De combien ?** — le dimensionnement, donc le risque de la ligne et son poids actuel.

Elle affiche des **chiffres nus**, sans verdict ni phrase de synthèse : l'interprétation reste au
porteur. Les seuils et les pièges de lecture vivent dans les dialogues d'aide, pas dans un jugement
affiché.

Hors périmètre, décidé et à ne pas réintroduire sans nouvelle discussion :

- Le simulateur de renfort (« si je remets X €, mon PRU devient »). Interactif, à état, c'est une
  feature à part entière.
- La volatilité annualisée — doublon de l'ATR en % pour cet usage, moins lisible.
- La MM50 et le croisement MM50/MM200 — signal d'un horizon plus court que le nôtre.
- Le bêta contre un indice — demande une série de référence que l'application ne stocke pas.

## Ce que la section affiche

```
Analyse
  PRU                    80,00 €
  Écart au PRU           +25,0 %
  — Tendance —
  MM200                  82,40 €
  Cours vs MM200         −11,2 %
  RSI 14                 38
  Plus-haut 52 s.        94,10 €
  Sous le plus-haut      −22,2 %
  — Risque —
  ATR 14                 1,9 %
  Max drawdown           −31,4 %
  Poids du portefeuille  4,2 %
```

La section n'apparaît **que si l'instrument est détenu**. Sans position il n'y a ni PRU ni poids, et
ce qui reste n'est plus une analyse de ligne mais un bulletin de marché.

Le PRU quitte `FiguresSection` pour la tête de cette section. `FiguresSection` conserve son autre
cas, seul rescapé de `heroMeta()` : la ligne « au 01/07/2026 » d'un instrument suivi sans position.

## Données disponibles

Vérifié en base le 2026-08-30 : `asset_prices` porte 33 663 lignes, **`open`, `high`, `low`,
`close` et `volume` remplis à 100 %**, ~1 350 séances par instrument sur cinq ans (le plus jeune à
432). MM200 et vrai ATR (True Range avec plus-haut et plus-bas de séance) sont donc calculables
sans approximation sur les clôtures seules.

La fenêtre de lecture reste celle de la page : `PriceHistoryWindow::since()`, cinq ans, partagée
par la fiche en ligne et l'instantané hors ligne.

## Architecture

### Calculateurs purs — `app/Contexts/Market/Services/`

Dossier à créer ; `Market` possède les cours. Doctrine `Services/` appliquée telle quelle : entrées
nues ou Datas du contexte, aucun Eloquent, aucun port, aucun conteneur ; test co-localisé
construisant l'objet au `new`, sans base de données.

| Classe | Signature | Notes |
| --- | --- | --- |
| `MovingAverage` | `of(list<float> $closes, int $period): ?float` | Moyenne simple des `$period` dernières clôtures. `null` si la série est plus courte. |
| `RelativeStrengthIndex` | `of(list<float> $closes, int $period = 14): ?float` | **Lissage de Wilder**, pas moyenne simple : la première moyenne de gains/pertes est simple sur `$period`, les suivantes valent `(précédente × ($period − 1) + valeur) / $period`. `null` si série trop courte. Sans aucune baisse sur la fenêtre, le RSI vaut 100 (pas de division par zéro). |
| `AverageTrueRange` | `of(list<TrueRangeBar> $bars, int $period = 14): ?AtrData` | True Range = `max(high − low, |high − closeVeille|, |low − closeVeille|)`, moyenné à la Wilder. `AtrData` porte la valeur absolue **et** son pourcentage du dernier cours, pour que ce ratio n'ait qu'un seul site de calcul. |
| `FiftyTwoWeekRange` | `of(list<float> $closes): ?FiftyTwoWeekData` | Plus-haut, plus-bas et distance au plus-haut en %, sur les **252 dernières séances** (ou toute la série si plus courte). |

Nouvelles Datas de `Market\Datas` : `TrueRangeBar` (high, low, close), `AtrData` (value, percent),
`FiftyTwoWeekData` (high, low, gapPct).

### Deux réutilisations, pas deux formules de plus

- **Max drawdown** : `Valuation\Services\Drawdown::of($labels, $values)` fait exactement ce calcul
  sur une série et est déjà testé. Précédent maison assumé : `ValuationHistory::drawdownFor()`
  compose déjà une action et ce service depuis `Infrastructure/`. On lui passe labels et clôtures.
- **Poids dans le portefeuille** : `Portfolio\Services\Concentration` normalise des valeurs mais ne
  rend pas le poids d'une ligne. Ajouter
  `Portfolio\Services\PositionWeight::of(?float $value, list<?float> $values): ?float` — même
  doctrine, quelques lignes, `null` si la valeur manque ou si le total est nul. Le poids se
  rapporte au **portefeuille entier**, toutes expositions confondues : c'est l'échelle à laquelle
  se décide la taille d'une louche.

### Transport — port et adaptateur

Nouveau port `MarketView\Ports\InstrumentAnalysisPort` et son adaptateur
`MarketView\Infrastructure\InstrumentAnalysis`. Un port dédié plutôt qu'une méthode de plus sur
`MarketDataPort` : cette lecture croise trois voisins, elle n'est pas une lecture de marché.

L'adaptateur **compose**, il ne calcule pas — règle MarketView :

1. lit les barres via `Market\Contracts\PriceRepositoryContract` sur la fenêtre `PriceHistoryWindow` ;
2. appelle les quatre calculateurs de `Market\Services` ;
3. passe labels + clôtures à `Valuation\Services\Drawdown` ;
4. tire la position et les valeurs de marché du portefeuille de `PortfolioOverviewPort`, puis
   `Portfolio\Services\PositionWeight`.

Aucune formule ne s'écrit dans l'adaptateur : les écarts en pourcentage (`pruGapPct`,
`ma200GapPct`) sont rendus par les calculateurs ou par la Data, jamais improvisés ici.

### La Data

`MarketView\Datas\InstrumentAnalysisData` — nom distinct de `MarketView\Datas\AnalysisData`, déjà
pris par l'analyse d'exposition (concentration + contributions).

Champs, dans cet ordre de sérialisation :

```
pru, pruGapPct, ma200, ma200GapPct, rsi14,
high52w, high52wGapPct, atr, atrPct, maxDrawdown, portfolioWeightPct
```

Tous nullables : un instrument jeune n'a pas de MM200, un instrument plat pas d'ATR utile.

**L'ordre des clés de `jsonSerialize()` est un contrat** : `SnapshotController` publie
`sha1(json_encode($body))` comme version du blob hors ligne, et un ordre différent le ferait
retélécharger à tous les clients.

### Branchement

- `AssetController` : prop `analysis`, en `Inertia::defer()` — comme `priceHistory`, puisqu'elle
  lit cinq ans de barres. Absente quand l'instrument n'est pas détenu.
- `BuildMarketViewSnapshot::assetPage()` : même prop, pour que la section vive hors ligne.
- Page `Asset/Show.vue` : `aheadOfNetwork()` sur la prop, comme `priceHistory` et `valuation`.

## Écran

### Composants

- `components/instrument/AnalysisSection.vue` — bâti sur `CollapsibleSection`
  (`section="analysis"`, titre « Analyse »), replié à l'arrivée comme Performances et Secteurs.
  Gère les trois états de la prop différée sur le modèle de `PerformancesSection.vue` : contenu,
  `<Deferred>` avec squelette pulsant en `#fallback`, et « Données indisponibles hors-ligne » en
  `#rescue`.
- `components/instrument/AnalysisRow.vue` — une ligne : libellé, bouton `Info`, valeur en
  `tabular-nums` sur le bord droit.
- `components/IndicatorInfoDialog.vue` — un seul composant, prop `indicator: IndicatorId`.

### Mise en forme

`resources/js/lib/analysis.ts` transforme la Data en groupes de lignes — fonction pure, testée en
vitest, même pattern que `heroMeta()` dans `lib/instrument.ts`.

- Une valeur absente rend `—` plutôt que de masquer sa ligne (précédent : le PRU sans prix de
  revient).
- Un groupe dont toutes les valeurs manquent disparaît.
- Les pourcentages signés passent par `gainClass()` là où le signe porte un sens de gain ou de
  perte (écart au PRU), et restent neutres là où il n'en porte pas (écart à la MM200, ATR).

### Les aides

Le bouton `Info` de chaque ligne ouvre son dialogue. Il n'ouvre que ça : la section se replie par
son titre, donc pas de conflit de zones cliquables comme dans `CollapsibleSection`. Bouton discret
(`variant="ghost"`, `size="icon-sm"`, `text-muted-foreground`), `aria-label` explicite du type
« Comment lire le RSI 14 ».

Les textes vivent en données dans `resources/js/lib/indicatorHelp.ts` :

```ts
type IndicatorHelp = {
    title: string;      // « Comment lire le RSI »
    subtitle: string;   // la ligne qu'il explique
    body: string[];     // deux ou trois paragraphes
    formula?: string;   // encart mono, comme l'aide des performances
    caveat?: string;    // la limite de lecture, en muted
};
```

Entorse assumée à `PerformanceInfoDialog.vue`, qui porte ses deux variantes en dur dans son
template : à dix indicateurs le template deviendrait illisible. En données, un test garantit aussi
que chaque indicateur affiché a son aide — aucun bouton n'ouvre du vide.

Le fond de chaque aide (la rédaction finale se fait à l'implémentation) :

- **PRU** — prix moyen payé par part. Ta référence, pas un signal.
- **Écart au PRU** — gain latent par part. Ne dit rien du bon moment pour agir : une ligne à +80 %
  peut rester une bonne affaire, une ligne à −20 % un piège.
- **MM200** — moyenne des 200 dernières clôtures, la tendance de fond.
- **Cours vs MM200** — au-dessus, tendance haussière ; en dessous, le marché paie moins cher que sa
  moyenne longue. Piège : un titre peut rester des mois sous sa MM200 et continuer de baisser.
- **RSI 14** — vigueur des hausses face aux baisses sur 14 séances, de 0 à 100. Sous 30
  « survendu », au-dessus de 70 « suracheté ». Piège : en tendance forte, le RSI colle aux extrêmes
  pendant des semaines — ce n'est pas un compte à rebours.
- **Plus-haut 52 semaines** et **Sous le plus-haut** — le sommet de l'année et ta distance à lui,
  pour situer le prix dans son propre historique.
- **ATR 14** — amplitude quotidienne moyenne, en % du cours, donc comparable d'un instrument à
  l'autre. « 1,9 % » veut dire qu'une journée ordinaire bouge de 1,9 %. Plus l'ATR est haut, plus la
  ligne doit être petite à risque égal.
- **Max drawdown** — la pire chute depuis un sommet sur l'historique. Le risque déjà vécu, pas une
  prévision.
- **Poids du portefeuille** — part de la ligne dans ta valeur totale. Le chiffre qui répond
  vraiment à « je renforce de combien ».

## Tests

Du plus bas au plus haut :

1. **Un test Pest unitaire co-localisé par calculateur** — `MovingAverageTest`,
   `RelativeStrengthIndexTest`, `AverageTrueRangeTest`, `FiftyTwoWeekRangeTest`,
   `PositionWeightTest`. Séries construites à la main, valeurs attendues calculées à la main. Le
   RSI porte un **cas de référence connu** : c'est là que le lissage de Wilder se trompe
   silencieusement.
2. **Cas limites obligatoires** — série plus courte que la période, série plate (RSI à 100, ATR
   nul), une seule barre (pas de clôture veille), valeurs nulles ou négatives.
3. **`InstrumentAnalysisTest`** — l'adaptateur, avec factories : une position connue donne des
   chiffres connus ; un instrument non détenu ne rend rien.
4. **Feature test `AssetController`** — la prop `analysis` existe, elle est différée, elle est
   absente sans position.
5. **Test de l'instantané hors ligne** — la page actif du blob porte `analysis`.
6. **vitest** — `lib/analysis.ts` (groupes, `—` sur valeur absente, groupe vide masqué) et
   `lib/indicatorHelp.ts` (chaque `IndicatorId` a une entrée non vide).
7. **Test navigateur** — la section rend ses lignes sans erreur JS, et un dialogue ouvert depuis
   une ligne affiche son titre.

## Séquence d'implémentation

1. Calculateurs `Market\Services` + leurs Datas, en TDD, sans rien brancher.
2. `Portfolio\Services\PositionWeight`.
3. `InstrumentAnalysisData`, port, adaptateur.
4. Branchement `AssetController` + snapshot hors ligne.
5. `lib/analysis.ts` et `lib/indicatorHelp.ts`.
6. Composants Vue, PRU déplacé hors de `FiguresSection`.
7. Test navigateur de bout en bout.
