# Roadmap — Indicateurs Risque & Performance

Objectif : aller au-delà du suivi basique vers un dashboard analytique complet, différenciant sur DCA et risque ajusté.

---

## Tier 1 — Manques critiques

> Indicateurs absents pour un suivi sérieux.

### 1. Max Drawdown

| Indicateur | Description |
|---|---|
| **MDD historique** | Pire baisse peak-to-trough depuis l'ouverture |
| **Drawdown actuel** | Distance au plus haut historique actuel |
| **Time underwater** | Durée depuis le dernier plus haut |

**Formule :**
```
MDD = (peak_value - trough_value) / peak_value × 100
Drawdown actuel = (all_time_high - current_value) / all_time_high × 100
```

---

### 2. Performance vs Benchmark configurable

Comparaison côte à côte sur les mêmes périodes (1M, 3M, 6M, 1Y, depuis ouverture).

- Choix benchmark dans settings : MSCI World, S&P 500, CAC 40, STOXX 600, Nasdaq, personnalisé
- Courbes superposées portefeuille vs benchmark
- Alpha = perf portefeuille - perf benchmark sur même période

---

### 3. CAGR / TWR depuis ouverture

```
CAGR = (valeur_finale / valeur_initiale)^(1/années) - 1
```

Répond à : "Est-ce que je fais 8 % ou 15 % par an ?"

---

### 4. TWR vs MWR (XIRR)

| Méthode | Calcul | Usage |
|---|---|---|
| **TWR** (Time-Weighted) | Enchaîne sous-périodes, neutralise flux | Perf intrinsèque de la stratégie |
| **MWR** (Money-Weighted / XIRR) | IRR sur flux réels | Rendement sur ton argent investi |

Pour un investisseur DCA : les deux diffèrent si les apports arrivent au mauvais moment.

---

### 5. Décomposition de la valorisation

```
Valorisation = Apports cumulés + Plus-value latente + Plus-value réalisée
```

Visualisation de la contribution marché vs apports.

---

## Tier 2 — Panel risque avancé

### Ratios ajustés du risque

| Ratio | Formule | Question |
|---|---|---|
| **Sharpe** | `(R_p - R_f) / σ_p` | Perf par unité de risque total |
| **Sortino** | `(R_p - R_f) / σ_downside` | Perf par unité de risque baissier seulement |
| **Calmar** | `CAGR / \|MDD\|` | Perf annualisée par rapport à la pire perte |

`R_f` = taux sans risque configurable (ex: OAT 10 ans, €STR)

### Allocation agrégée

- **Géographique** : US / Europe / Japon / EM / autres — via composition réelle des ETF (JustETF API)
- **Sectorielle** : tech, santé, finance, énergie…
- **Par classe d'actifs** : actions / obligations / cash

### Concentration

- **HHI** (Herfindahl-Hirschman Index) : `Σ(poids_i²)` — 1 = mono-ligne, proche 0 = diversifié
- Nombre effectif de positions : `1 / HHI`

---

## Tier 3 — Mode avancé (toggle)

| Indicateur | Répond à |
|---|---|
| **Beta vs benchmark** | À quel point tu suis le marché |
| **Information Ratio** | Skill du stock-picking vs benchmark |
| **Tracking Error** | Volatilité de l'écart au benchmark |
| **Up/Down Capture** | Tu captures combien des hausses / baisses |
| **VaR 95% / CVaR** | Risque de perte extrême |
| **Skewness / Kurtosis** | Forme de la distribution des rendements |
| **Best / Worst month** | Bornes empiriques |
| **Top contributors / detractors** | Quelles lignes tirent / pénalisent |

---

## Tier 4 — DCA-aware (différenciant fort)

### Comparateur DCA contrefactuel

> "Si tu avais mis chaque apport dans le MSCI World, tu aurais X € au lieu de Y €"

- Visualisation du gap d'opportunité (positif ou négatif)
- Calcul rétroactif : reconstruire un portefeuille fantôme avec les mêmes dates/montants d'apport dans le benchmark

### Cohérence d'apport

- Apport moyen mensuel
- Régularité du DCA (variance des apports)
- Mois manqués / sautés

### Projection forward enrichie

- Avec CAGR actuel + apport moyen → projection 5/10/20 ans
- Monte Carlo (déjà implémenté) → IC 90%

---

## Priorités MVP d'amélioration

```
1. Performance vs benchmark configurable   ← impact immédiat sur l'interprétation
2. Max Drawdown + drawdown actuel          ← gap critique côté risque
3. CAGR depuis ouverture                   ← manque énorme
4. TWR vs MWR distinction                  ← différenciateur DCA
5. Sharpe / Sortino                        ← entrée dans le risque ajusté
```

---

## Hiérarchie UI cible

```
Vue principale     → valorisation, perf, drawdown, vs benchmark
Vue risque         → vol, MDD, Sharpe, Sortino, Calmar
Vue allocation     → géo, secteurs, concentration HHI
Vue avancée toggle → tier 3 complet
```

---

## Implémentation — Pointeurs

| Feature | Service à créer / modifier | Données requises |
|---|---|---|
| Max Drawdown | `DrawdownCalculator` | `ValuationHistory` time series |
| Benchmark | `BenchmarkService` + `BenchmarkPrice` table | Prix quotidiens benchmark |
| CAGR / TWR | Étendre `PortfolioPerformanceCalculator` | Flux + valorisations horodatées |
| MWR / XIRR | Nouveau `XirrCalculator` | `Transaction` dates + montants |
| Sharpe / Sortino | `RiskRatiosCalculator` | Rendements journaliers + R_f |
| Calmar | Dépend de `DrawdownCalculator` + CAGR | — |
| HHI | `ConcentrationCalculator` | Valorisation par ligne |
| Contrefactuel DCA | `CounterfactualCalculator` | `Transaction` + `BenchmarkPrice` |

---

*Dernière mise à jour : 2026-05-06*
