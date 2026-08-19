# argent

Application web de suivi de portefeuille financier personnel : valorisation, évolution, performances, répartition sectorielle et fiche détaillée par instrument. Les prix et secteurs sont récupérés auprès de Yahoo Finance via des scripts Python.

## Stack technique

| Couche | Technologie | Version |
|---|---|---|
| Langage | PHP | ^8.5 |
| Framework | Laravel | 13 |
| Pont SPA | Inertia (`inertiajs/inertia-laravel` + `@inertiajs/vue3`) | 3 |
| Front | Vue | 3.5 |
| Typage front | TypeScript | 5.7 |
| CSS | Tailwind CSS | 4 |
| Composants | Reka UI, Lucide, TanStack Table | — |
| Graphiques | ECharts | 6 |
| Bundler | Vite | 7 |
| Base de données | SQLite | — |
| Données de marché | Python (uv + `.venv`), Yahoo Finance | — |
| Tests PHP | Pest (+ plugins Laravel et Browser) | 5 |
| Tests JS / PWA | Vitest, Playwright | 4 / 1.61 |
| Analyse statique | Larastan / PHPStan | 3 / 2.2 |
| Formatage | Laravel Pint | 1 |
| Debug | Laravel Debugbar, Pail, Opcodes Log Viewer | — |
| Outillage agents | Laravel Boost | 2 |
| Serveur local | Laravel Herd | — |

## Fonctionnalités

- **Dashboard** (`/`) — Sections valorisation, évolution, performances, répartition sectorielle et catalogue d'instruments, chargées indépendamment via les props différées d'Inertia
- **Fiche instrument** (`/instruments/{id}`) — Historique de prix, position détenue, transactions et répartition sectorielle
- **Positions dérivées** — `holdings_projection` reconstruite automatiquement à chaque écriture de transaction, avec calcul du gain réalisé
- **Données de marché** — Commandes `market:sync-prices` et `market:sync-sectors`, adossées aux scripts Python `fetch_prices*.py` / `fetch_sectors.py`
- **PWA** — Manifeste, service worker et page hors-ligne (`routes/pwa.php`)
- **Jeux de données** — Seeders de démonstration et `BackupSeeder`, qui rejoue un dump MySQL de production

L'application n'a **pas d'authentification** : aucune route de connexion n'est déclarée et le dashboard retombe sur le premier utilisateur en base (`auth()->user() ?? User::query()->first()`). Les rôles `admin` / `user` existent en base (`Role` enum) mais ne gouvernent aucun accès.

## Accès

- **URL locale** : https://argent.test/ (Laravel Herd)

## Installation

```bash
composer setup
```

Enchaîne `composer install`, la copie de `.env`, `key:generate`, `migrate --force`, puis `npm install` et `npm run build`.

## Développement

```bash
composer run dev
```

Lance en parallèle : le serveur Laravel, le worker de queue, les logs (Pail) et Vite.

## Tests

```bash
php artisan test --compact       # suite complète
composer test                    # Pest en parallèle avec Test Impact Analysis
composer check                   # Pint + tests JS (bun) + suite PHP
```

## Linting

```bash
vendor/bin/pint --dirty
```

## Documentation

Voir [docs/README.md](docs/README.md) : architecture DDD, contexte Market, modèle de données, shared kernel Python et état de la refonte.
