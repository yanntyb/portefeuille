# Graphe fusionné base 100 — fiche instrument

Date : 2026-07-12
Contexte : `InstrumentView` (front) + `Valuation` (données)

## Problème

La fiche instrument (`Instruments/Show.vue`) affiche aujourd'hui **deux** graphes empilés :

1. **Cours** — prix unitaire du titre sur 12 mois (`priceHistory`).
2. **Valeur vs Investi** — valeur de ma position et capital investi cumulé, en euros, sur tout l'historique (`valuation`).

Quand l'utilisateur détient une position, on veut **un seul** graphe qui combine cours, valeur et investi.

## Décision

Quand une position est détenue (série `valuation` non vide), afficher **un unique graphe en base 100** qui remplace les deux graphes actuels. Le graphe Cours 12 mois disparaît dans ce cas — le cours est intégré à la fusion.

Quand aucune position n'est détenue, comportement inchangé : seul le graphe **Cours** 12 mois est affiché (pas de valorisation possible).

### Le graphe fusionné

- **Trois courbes**, chacune indexée à 100 sur son premier point :
  - **Cours** — prix unitaire du titre.
  - **Valeur** — valeur de ma position.
  - **Investi** — capital cumulé investi.
- **Une seule échelle Y** (base 100), pas d'axe secondaire.
- **Axe X commun** = labels de la série `valuation` (historique complet depuis la première transaction).
- **Type** : `line` (courbes, pas d'aires empilées).
- **Format axe Y et tooltip** : performance signée par rapport à 100, ex. `+28 %`, `-12 %` (valeur base 100 `128` → `+28 %`).

Lecture attendue :
- écart Valeur / Cours = effet des versements échelonnés (DCA) ;
- écart Valeur / Investi = performance des apports.

## Modifications

### Backend — `app/Contexts/Valuation`

**`Datas/ValuationSeriesData.php`**
- Ajouter `list<float> $prices` au constructeur (cours unitaire aligné par label).
- `empty()` : passer `prices = []`.
- `jsonSerialize()` : exposer `prices`.

**`Services/ValuationCalculator.php` — `calculate()`**
- Capturer un tableau `$closes[]` parallèle à `$valuations` / `$invested` : cours unitaire du titre à chaque date (déjà disponible via `$lastClose[$assetId]`).
- Cas mono-actif (usage fiche instrument) : `$closes[]` = close du seul actif ce jour-là (dernier close connu). Cas multi-actifs : on retient le close du premier actif — champ non consommé par le dashboard, sans impact.
- Downsampler `$closes` avec les **mêmes indices** que `$valuations` / `$invested`.
- Passer `$closes` (renommé `prices`) à `ValuationSeriesData`.

**`Actions/BuildAssetValuationSeries.php`**
- Aucune signature à changer si le champ est porté par `ValuationSeriesData` ; vérifier que le retour propage bien `prices`.

### Frontend — `resources/js/Pages/Instruments/Show.vue`

- Interface `ValuationSeries` : ajouter `prices: number[]`.
- Base 100 calculée côté front pour chaque série :
  `base100(serie) = serie.map(v => serie[0] === 0 ? 0 : v / serie[0] * 100)`.
- Nouveau graphe `line` à 3 séries : Cours, Valeur, Investi (base 100).
  - Couleurs : Valeur `#4f46e5` (indigo), Investi `#64748b` (slate), Cours `#10b981` (émeraude).
  - `yaxis.labels.formatter` et `tooltip.y.formatter` : `v => signedPct(v)` où `signedPct(128) = '+28 %'`, `signedPct(88) = '-12 %'`.
  - Titre : « Performance » / description « Base 100 depuis la première transaction ».
- Condition template :
  - `hasValuation` (position détenue) → graphe base 100 **seul**.
  - sinon → graphe **Cours** 12 mois seul (actuel).
- Supprimer le rendu du graphe « Valeur vs Investi » en euros et le graphe « Cours » quand `hasValuation`.

## Tests

**Unit / feature — `ValuationCalculator`**
- `calculate()` renvoie `prices` de même longueur que `valuations` / `invested`, aligné sur les labels.
- Downsampling : `prices` suit les mêmes indices que les autres séries.
- `ValuationSeriesData::empty()` a `prices = []`.

**Component — `Instruments/Show.vue`**
- Position détenue → un seul graphe base 100, pas de graphe Cours 12 mois ni « Valeur vs Investi » euros.
- Pas de position → graphe Cours 12 mois seul.
- Vérifier la normalisation base 100 (premier point = 100 / `+0 %`) et le format `+28 %`.

## Hors périmètre

- Toggle € / base 100.
- Axe Y secondaire.
- Modification du graphe investi cumulé du dashboard.

---

## Révision v2 (2026-07-12) — filtres range + granularité, server-side

La v1 (un graphe base 100 unique) est remplacée, quand une position est détenue, par :

- **Deux graphes en grid 2 colonnes** : à gauche **Cours** (base 100), à droite **Valeur / Investi** (base 100).
- **Deux filtres synchronisés** pilotant les deux graphes :
  - **Range** (fenêtre) : `1M`, `6M`, `1A`, `Max`.
  - **Granularité** : `Jour`, `Semaine`, `Mois`.
- **Base 100 dynamique** relative au **début de la fenêtre affichée** : le serveur ne renvoie que les points de la fenêtre, le client ancre 100 sur le premier point retourné. Changer un filtre recalcule les pourcentages.

### Architecture retenue : server-side à la demande (Inertia partial reload)

Sur changement de filtre : `router.reload({ only: ['valuation'], data: { range, granularity }, preserveState: true, preserveScroll: true })`. Le serveur fenêtre + agrège + renvoie uniquement les points nécessaires. Payload petit, buckets exacts côté serveur, scalable. Coût : un round-trip par changement (barre de progression Inertia + graphes atténués pendant le fetch).

La normalisation **base 100 reste client-side** (réutilise `base100` / `signedPct` de la v1) : le serveur renvoie les valeurs brutes (€ / cours) de la fenêtre, le client rebase sur le premier point.

### Backend (contexte Valuation)

- **Enums** `ValuationRange` (`OneMonth`/`SixMonths`/`OneYear`/`Max`, backed string ; méthode `months(): ?int` → 1/6/12/null ; `fromRequest(?string): self` défaut `Max`) et `ValuationGranularity` (`Day`/`Week`/`Month` ; `fromRequest` défaut `Month`). Suivent le pattern `TransactionType` (backed string, `values()`, `getLabel()`).
- **`ValuationCalculator::calculateDaily(transactions, prices): ValuationSeriesData`** : extraction de la série **quotidienne pleine** (sans downsampling). `calculate(maxPoints = 200)` devient `calculateDaily` + downsampling — comportement du **dashboard/portefeuille inchangé**.
- **`ValuationCalculator::windowAndAggregate(ValuationSeriesData $daily, ValuationRange, ValuationGranularity): ValuationSeriesData`** : filtre la fenêtre (`range.months` mois avant la dernière date ; `Max` = tout), puis agrège par bucket de granularité en gardant le **dernier point du bucket** (dernier close, valeur / investi cumulés en fin de période). Le label conservé est la date réelle du dernier point du bucket.
- **`BuildAssetValuationSeries::__invoke(userId, assetId, ValuationRange $range, ValuationGranularity $granularity)`** = `calculateDaily` → `windowAndAggregate`. Les prix restent récupérés depuis la première transaction (cumuls corrects avant le début de fenêtre), le fenêtrage s'applique à la sortie.
- **`InstrumentDetailController`** : lit `range` / `granularity` en query (`ValuationRange::fromRequest` / `ValuationGranularity::fromRequest`, défaut si absent/invalide), les passe au prop déféré `valuation`.

### Frontend (`Instruments/Show.vue`)

- Deux segmented controls (Range, Granularité) au-dessus de la grid, labels FR (`1M 6M 1A Max` / `Jour Sem Mois`), état actif surligné.
- Refs `selectedRange` / `selectedGranularity` (défauts `Max` / `Mois`). Sur changement : `router.reload(...)` ci-dessus, en renvoyant **les deux** paramètres.
- Ref `reloading` (via `onStart`/`onFinish` du reload) : atténue les graphes pendant le fetch.
- Grid `grid gap-4 lg:grid-cols-2` : Card **Cours** (série `base100(prices)`), Card **Valeur / Investi** (séries `base100(valuations)` + `base100(invested)`).
- Base 100 ancrée sur le premier point retourné → pourcentages relatifs au début de la fenêtre.
- Pas de position → inchangé (Cours 12 mois brut €, sans filtres).

### Tests v2

- **Unit** `ValuationRange` / `ValuationGranularity` : `months()`, `fromRequest` (défaut, valeur invalide), `getLabel`.
- **Unit** `calculateDaily` : série quotidienne pleine (pas de cap), `prices` inclus ; `calculate` conserve son downsampling (portefeuille intact).
- **Unit** `windowAndAggregate` : fenêtre `1M`/`6M`/`Max` filtre correctement ; agrégation `Week`/`Month` garde le dernier point par bucket ; `Day` = pas d'agrégation.
- **Feature** `InstrumentDetailController` : accepte `?range=&granularity=`, prop `valuation` déféré chargé ; valeur invalide → défaut (pas d'erreur).
- **Front** : `bun run typecheck` + vérif visuelle multi-filtres (changement range/granularité → reload → graphes mis à jour et rebasés).

### Hors périmètre v2

- Zoom interactif ApexCharts / brush.
- Filtres sur le graphe du dashboard.
- Streaming temps réel (SSE/websocket).
- Résolution adaptative purement client (choix : agrégation côté serveur).
