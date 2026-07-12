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
