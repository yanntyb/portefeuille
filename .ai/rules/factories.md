---
paths:
  - '**/Factories/**'
---

# Factories

## Fixer ticker (et isin si le jeu alimente le filet) sur un nouvel Instrument::factory()
`Market\Factories\InstrumentFactory` tire `isin` et `ticker` au sort (`fake()->optional()->...`). Les fixtures globales de `tests/Pest.php` figent `ticker` mais pas `isin`, et `tests/Feature/SnapshotInvariantTest.php` ne neutralise qu'`isin` (via `normalizeSnapshotBody()`).

Tout nouvel `Instrument::factory()->create([...])` ajouté à un jeu de données qui alimente `SnapshotInvariantTest` (directement ou via une fixture partagée) doit fixer son `ticker` explicitement, sinon le hash de référence devient instable d'un lancement à l'autre.
