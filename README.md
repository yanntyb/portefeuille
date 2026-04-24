# Portefeuille

Application web de gestion de portefeuilles financiers personnels. Elle permet de suivre ses investissements, analyser la performance de ses titres, simuler des scénarios et rééquilibrer ses allocations.

## Stack technique

| Couche | Technologie | Version |
|---|---|---|
| Backend | PHP | 8.5 |
| Framework | Laravel | 12 |
| Admin / UI | Filament | 5 |
| Composants UI | Flux UI (Livewire) | 2 |
| Réactivité | Livewire | 4 |
| CSS | Tailwind CSS | 4 |
| Bundler | Vite | 7 |
| Runtime JS | Bun | — |
| Tests | Pest | 4 |
| Linter | Laravel Pint | 1 |
| Logs | Opcodesio Log Viewer | 3 |
| Impersonation | Filament Impersonate | 5 |
| Serveur local | Laravel Herd | — |

## Fonctionnalités

- **Portefeuilles (Wallets)** — Création et configuration de portefeuilles avec gestion des frais récurrents
- **Titres (Securities)** — Suivi des titres financiers avec prix, secteurs et historique
- **Transactions** — Enregistrement des achats/ventes avec calcul automatique des performances
- **Profils d'allocation** — Définition de profils cibles pour le rééquilibrage de portefeuille
- **Calculateur de rééquilibrage** — Outil de calcul des ajustements nécessaires pour atteindre l'allocation cible
- **Simulateur** — Tableau de simulation de scénarios d'investissement
- **Dashboard** — Vue d'ensemble avec widgets de statistiques et graphiques
- **Gestion des utilisateurs** — Rôles (admin/utilisateur), impersonation, système d'invitation par lien
- **Feedback** — Collecte de retours utilisateurs

## Accès

- **URL** : https://argent.test/
- **Email** : `test@example.com`
- **Mot de passe** : `password`

## Installation

```bash
composer setup
```

## Développement

```bash
composer run dev
```

Lance en parallèle : le serveur Laravel, le worker de queue, les logs (Pail) et Vite.

## Tests

```bash
php artisan test --compact
```

## Linting

```bash
vendor/bin/pint --dirty
```
