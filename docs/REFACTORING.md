# Guide de Simplification — Architecture Actuelle

Ce document identifie les duplications et complexités à éliminer **avant** ou **pendant** l'implémentation de la roadmap. Travailler sur base saine évite de dupliquer les problèmes existants.

---

## Architecture actuelle

```mermaid
graph TD
    subgraph Pages["Pages Filament"]
        AP["AccountPage (abstract)\n+ getTotalValuation()\n+ computeAnnualizedReturn()\n+ computePortfolioVolatility()\n+ computeSecurityVisibility()"]
        WP["WalletPage\n+ configureSimulationAction()\n+ configureFeesAction()"]
        DB["Dashboard\n+ loadPrices()"]
        ES["EditSecurity (abstract)"]
        EWS["EditWalletSecurity\n⚠ copie content() entier\njuste pour walletId"]
        AP --> WP
        ES --> EWS
    end

    subgraph TripletsWidgets["Triplets dupliqués (même view, même contrat)"]
        G1["GainStatsOverview\n(portefeuille)"]
        G2["DashboardGainStatsOverview\n(home)"]
        G3["SingleSecurityGainStatsOverview\n(titre)"]
        P1["PerformanceStatsOverview\n(portefeuille)"]
        P2["DashboardPerformanceStatsOverview\n(home)"]
        P3["SingleSecurityPerformanceStatsOverview\n(titre)"]
        V1["ValuationStatOverview\n(portefeuille)"]
        V2["DashboardValuationWidget\n(home)"]
        V3["SingleSecurityValuationStatOverview\n(titre)"]
        G1 & G2 & G3 -. "même view\ngain-stats-overview" .- shared1[" "]
        P1 & P2 & P3 -. "même view\nperformance-stats-overview" .- shared2[" "]
        V1 & V2 & V3 -. "même view\nvaluation-stats-overview" .- shared3[" "]
    end

    subgraph SingleSecurityRedondant["Widgets SingleSecurity — 5 queries identiques par page"]
        SS1["SingleSecurityValuationStatOverview\ncomputeStats() → query TX"]
        SS2["SingleSecurityGainStatsOverview\ncomputeStats() → query TX"]
        SS3["SingleSecurityPlusValueWidget\ncomputeStats() → query TX"]
        SS4["SingleSecurityFeesStatsWidget\ncomputeStats() → query TX"]
        SS5["SingleSecurityPriceStatsWidget\ncomputeStats() → query TX"]
    end

    subgraph Services["Services"]
        VC["VolatilityCalculator\n⚠ N+1: 1 query SecurityPrice\npar titre dans forWallet()"]
        PPC["PortfolioPerformanceCalculator\n⚠ getClosestPrice() O(P)\npar évaluation"]
        DDP["DashboardDataProvider\n✓ cache d'instance"]
        TA["TransactionAggregator\n⚠ computeDailyValuations()\nnon réutilisé dans ValuationChartWidget"]
    end

    subgraph Duplications["Duplications code"]
        D1["calcul total_quantity × close\ndupliqué ×11"]
        D2["Wallet::withoutGlobalScope...\ndupliqué ×4 widgets dashboard"]
        D3["schema Repeater frais\ndupliqué WalletPage + WalletsConfigPage"]
        D4["infoCorrelationAction()\ndupliqué ×2 widgets"]
        D5["refreshPrices() ≈ loadPrices()\nAccountPage vs Dashboard"]
    end
```

---

## Simplifications prioritaires

### S1 — Corriger le N+1 dans `VolatilityCalculator::forWallet()`

**Problème :** Pour chaque titre dans le portefeuille, une query `SecurityPrice` distincte est exécutée.

**Fichier :** `app/Services/VolatilityCalculator.php`, lignes 57–112

**Fix :**
```php
// Avant : N+1
foreach ($records as $record) {
    $prices = SecurityPrice::query()
        ->where('security_id', $record->id)  // ← une query par titre
        ->orderBy('date')
        ->pluck('close');
}

// Après : 1 query
$ids = $records->pluck('id')->all();
$allPrices = SecurityPrice::query()
    ->whereIn('security_id', $ids)
    ->orderBy('security_id')
    ->orderBy('date')
    ->get(['security_id', 'close'])
    ->groupBy('security_id')
    ->map(fn ($group) => $group->pluck('close')->map(fn ($v) => (float) $v)->values());

foreach ($records as $record) {
    $prices = $allPrices->get($record->id, collect());
    $sigma = $this->annualizedVolatility($prices);
    // ...
}
```

---

### S2 — Supprimer la surcharge de `EditWalletSecurity::content()`

**Problème :** `EditWalletSecurity` copie-colle `content()` en entier juste pour passer `walletId` non-null.

**Fichier :** `app/Filament/Resources/WalletSecurities/Pages/EditWalletSecurity.php`

**Fix :** Ajouter `protected function getWalletId(): ?int { return null; }` dans `EditSecurity`, overrider dans `EditWalletSecurity`, et utiliser `$this->getWalletId()` dans `content()` de la classe parente.

```mermaid
graph LR
    A["EditSecurity\ngetWalletId(): null\ncontent() utilise getWalletId()"]
    B["EditWalletSecurity\ngetWalletId(): $this->walletId\n← seule surcharge nécessaire"]
    A --> B
```

---

### S3 — Extraire `Security::currentValuation()` (duplication ×11)

**Problème :** Le pattern `total_quantity * close` avec null-checks est copié dans 11 fichiers.

**Fix :** Ajouter sur le modèle `Security` (après `scopeForWallet`) :
```php
public function currentValuation(): float
{
    $close = $this->latestPrice?->close;
    if ($close === null || $this->total_quantity === null) {
        return 0.0;
    }
    return (float) $this->total_quantity * (float) $close;
}
```
Puis remplacer les 11 occurrences par `$record->currentValuation()`.

---

### S4 — Uniformiser le chargement des wallets Dashboard (duplication ×4)

**Problème :** 4 widgets Dashboard rechargent les wallets indépendamment au lieu d'utiliser `DashboardDataProvider`.

**Fix :** Utiliser systématiquement `DashboardDataProvider::allSecurities()` partout dans les widgets Dashboard. La query `Wallet::withoutGlobalScope('user')->where('user_id', auth()->id())` ne doit exister qu'à l'intérieur de `DashboardDataProvider`.

---

### S5 — Consolider `computeStats()` dans les widgets SingleSecurity

**Problème :** Quand une page titre est chargée, `computeStats()` (qui query toutes les transactions) est appelée par chaque widget indépendamment — jusqu'à 5 fois pour la même page.

**Option A (simple) :** Utiliser `#[Computed]` de Livewire 4 sur `computeStats()` dans le trait `ComputesSingleSecurityStats` — le résultat sera mémoïsé pour le cycle de rendu.

```php
#[Computed]
protected function computeStats(): array { ... }
```

**Option B (propre) :** Extraire `SingleSecurityStatsService` injecté dans les widgets, avec cache par `security_id` + `wallet_id` sur la request.

---

### S6 — Extraire `PriceRefreshService`

**Problème :** `AccountPage::refreshPrices()` et `Dashboard::loadPrices()` font la même chose (check hasPriceless → fetchAndStorePricesBulk → dispatch `prices-updated`).

**Fix :** Nouveau service `PriceRefreshService::refreshForSecurities(Collection $securities): void`, appelé par les deux pages.

---

### S7 — Extraire le schema Repeater de frais

**Problème :** `WalletPage::configureFeesAction()` et `WalletsConfigPage` ont un schema Repeater identique.

**Fix :** Classe statique `App\Filament\Schemas\WalletFeesSchema::make(): array` retournant le schema Repeater, réutilisée dans les deux endroits.

---

## Plan de refactoring recommandé

```mermaid
gantt
    title Ordre d'exécution des simplifications
    dateFormat X
    axisFormat %s

    section Impact Haut
    S1 VolatilityCalculator N+1     :crit, s1, 0, 2
    S3 Security currentValuation    :s3, 0, 3

    section Impact Moyen
    S2 EditWalletSecurity           :s2, 2, 4
    S4 Wallets Dashboard            :s4, 2, 3
    S5 computeStats SingleSecurity  :s5, 3, 5

    section Impact Faible
    S6 PriceRefreshService          :s6, 4, 6
    S7 WalletFeesSchema             :s7, 4, 6
```

Faire S1 et S3 en priorité avant d'implémenter les features roadmap — elles réduisent le coût de chaque nouvelle feature.

---

## Architecture cible simplifiée

```mermaid
graph TD
    subgraph Pages_cible["Pages (après refactoring)"]
        AP2["AccountPage\ndelegate tout calcul → Services"]
        WP2["WalletPage extends AccountPage"]
        ES2["EditSecurity\ngetWalletId(): null"]
        EWS2["EditWalletSecurity\ngetWalletId(): $walletId\n← seule surcharge"]
        AP2 --> WP2
        ES2 --> EWS2
    end

    subgraph Services_cible["Services (après refactoring)"]
        VC2["VolatilityCalculator\n✓ 1 query bulk SecurityPrice"]
        PPC2["PortfolioPerformanceCalculator\n+ CAGR + MDD"]
        RC2["RiskRatiosCalculator\n(Sharpe, Sortino, Calmar)"]
        BC["BenchmarkService\n(nouveau)"]
        PRS["PriceRefreshService\n(nouveau)"]
    end

    subgraph Models_cible["Modèles (après roadmap)"]
        M1["Security\n+ currentValuation()"]
        M2["Benchmark (nouveau)"]
        M3["BenchmarkPrice (nouveau)"]
        M4["PortfolioValuation (optionnel)"]
        M2 --> M3
    end
```
