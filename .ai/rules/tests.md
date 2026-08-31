---
paths:
  - 'tests/**'
---

# Tests

## Fixer le nom d'un Wallet::factory() qui alimente SnapshotInvariantTest
`WalletFactory::definition()` tire `name` au sort (`fake()->unique()->words(2, true)`). Depuis que `HoldingRowData`/`HoldingLineData` exposent `walletName` dans le JSON, tout `Wallet::factory()->create()` sans nom explicite qui alimente (directement ou via une fixture partagée) `tests/Feature/SnapshotInvariantTest.php` rend le hash de référence instable d'un lancement à l'autre — même piège que `ticker`/`isin` documenté dans `.ai/rules/factories.md`. Fixer `name` explicitement dans toute fixture qui nourrit ce test (voir `portfolioFixture()`, `cryptoFixture()` et `seedSnapshotFixture()` dans `tests/Pest.php` / `SnapshotInvariantTest.php`).
