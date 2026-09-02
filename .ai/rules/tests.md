---
paths:
  - 'tests/**'
---

# Tests

## Fixer le nom d'un Wallet::factory() qui alimente SnapshotInvariantTest
`WalletFactory::definition()` tire `name` au sort (`fake()->unique()->words(2, true)`). Depuis que `HoldingRowData`/`HoldingLineData` exposent `walletName` dans le JSON, tout `Wallet::factory()->create()` sans nom explicite qui alimente (directement ou via une fixture partagée) `tests/Feature/SnapshotInvariantTest.php` rend le hash de référence instable d'un lancement à l'autre — même piège que `ticker`/`isin` documenté dans `.ai/rules/factories.md`. Fixer `name` explicitement dans toute fixture qui nourrit ce test (voir `portfolioFixture()`, `cryptoFixture()` et `seedSnapshotFixture()` dans `tests/Pest.php` / `SnapshotInvariantTest.php`).

## L'ordre d'insertion des transactions des fixtures alimente le hash d'instantané
Depuis que les trois Datas jumelles d'opération (`PortfolioView\TransactionLineData`, `ClassTransactionLineData`, `Wealth\WealthTransactionLineData`) exposent `id`, un auto-incrément entre dans le JSON de `tests/Feature/SnapshotInvariantTest.php`. Le hash reste stable — SQLite en mémoire sous `RefreshDatabase` remet la séquence à zéro à chaque test — mais **réordonner les créations de `Transaction::factory()`** dans `portfolioFixture()`, `cryptoFixture()` ou `seedSnapshotFixture()` déplacera le hash pour une raison qui n'a rien à voir avec la forme du JSON. Ajouter une transaction en fin de fixture est sans effet sur les précédentes ; en insérer une au milieu décale tout ce qui suit. Ne pas neutraliser `id` dans `normalizeSnapshotBody()` : c'est désormais une vraie clé de forme, le filet doit la voir.
