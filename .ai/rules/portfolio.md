---
paths:
  - 'app/Contexts/Portfolio/**'
---

# Portfolio

## HoldingValuator est le site unique du gain ; gainPct est null sur coût nul
`Portfolio\Services\HoldingValuator` est le seul endroit qui calcule la valorisation d'une position et son gain — à la ligne (`value()`) comme au total (`totals()`). `MarketView` et `Wealth` le demandent plutôt que de refaire la formule.

`gainPct` (donc `pct()`) rend `null`, jamais `0.0`, quand le coût est nul : un gain sans mise à laquelle le rapporter n'a pas de pourcentage, et « 0 % » mentirait. Vrai à la ligne comme au total.

`GetPortfolioPositions` et `GetPortfolioOverview` sont toutes deux liées en `scoped` dans `AppServiceProvider` et mémoïsent leurs lignes par utilisateur : une seule lecture du portefeuille sert tous les appelants d'une même requête HTTP. Un appel par position (une fiche par position détenue, par exemple) rouvrirait sinon un N+1 sur l'instantané.

## Les analyses lisent les positions, les listes lisent les lignes
`HoldingLineData` = une ligne par enveloppe ; `PositionLineData` = une position par actif, enveloppes confondues. La page liste affiche des lignes, la page analyse raisonne en positions. L'argument est la concentration : un titre à 30 % réparti sur deux comptes apparaîtrait comme deux lignes à 15 %, et le HHI le ferait passer pour une exposition modérée — l'enveloppe est un fait fiscal, pas un fait de marché. Conséquence assumée : un même titre apparaît une fois sur `/actions/analyse` et deux sur `/actions`, et tout rendu qui regroupe doit le dire. `GetPortfolioAnalysis::positionsOf()` refait ce regroupement au lieu de réutiliser `GetPortfolioPositions`, faute d'avoir étendu cette dernière avec un filtre par classe et le nom de l'actif — consolidation à trancher dans un chantier ultérieur, pas un correctif à faire au fil de l'eau.

## Les frais entrent une fois dans le coût, jamais deux
`CostBasis` ajoute les frais d'achat au coût : le PRU projeté sur une position est frais inclus, donc l'investi, le gain et le pourcentage de la fiche le sont aussi. `CalculateRealizedGain` n'en compte pas deux fois — les frais d'achat sont déjà dans le PRU, seuls ceux de la vente se soustraient encore.

`TransactionFlow` est le seul site du montant d'une ligne : achat majoré de ses frais, vente minorée des siens, toujours rendu positif. Les adaptateurs de transactions (`MarketView\Infrastructure\PortfolioTransactions`, `Wealth\Infrastructure\PortfolioLedger`) l'appellent au lieu de refaire `quantité × prix` ; le solde d'une année, somme des montants, en hérite côté front.

`Valuation\Services\ValuationCalculator` tient son propre PRU pour ses ventes : les frais y entrent aussi, sans quoi son investi et son coût diraient deux montants différents.

## AccountType est le seul site des règles d'enveloppe
`Portfolio\Enums\AccountType` porte tout ce que dit une enveloppe de détention : libellé, régime d'imposition, maturité, expositions admises. Les règles sont déclaratives — affichées, jamais appliquées à un calcul. L'application n'estime aucun impôt.

Le plafond de versement en est délibérément absent : `TransactionType` n'a que `Buy`/`Sell`, aucun mouvement d'espèces, donc aucun montant versé n'est calculable, et un plafond sans son solde ne renseigne sur rien. Un cumul de flux nets serait faux — réinvestir le produit d'une vente ne consomme pas de plafond.

Une position se lit par actif ET par enveloppe : `holdings_projection` a pour clé primaire `(asset_id, wallet_id)`, et `HoldingLineData` porte `walletId`. Tout regroupement côté front doit donc clé sur les deux — `instrumentList.ts` le faisait sur `assetId` seul et confondait les deux lignes d'un titre tenu dans deux comptes.

## GetPositionStock est le site unique du stock d'une position, et le gain réalisé se recalcule en grappe
`GetPositionStock` porte l'arithmétique « achats moins ventes », prix de revient compris. `ProjectHolding` la consomme, et le contrôle de survente de `TransactionRequest` aussi — avec son argument `$ignoringTransactionId`, sans lequel porter une vente de 4 à 5 se comparerait à un stock dont ses propres 4 titres sont déjà déduits.

`realized_gain` est une colonne STOCKÉE que `GetRealizedGains` relit telle quelle, et `CalculateRealizedGain` la dérive des achats antérieurs à la vente. Corriger ou supprimer un achat rend donc faux le gain de chaque vente postérieure : `RecomputeRealizedGains`, appelée par l'observateur, réécrit toutes les ventes de l'enveloppe — par `saveQuietly()`, sinon `updating` la rappellerait sans fin.

Survente refusée à la saisie, mais pour une raison précise : `ProjectHolding` SUPPRIME la ligne de position dès que la quantité tombe à zéro ou moins, si bien qu'une survente effacerait la position au lieu de la mettre en défaut. Le contrôle est volontairement aveugle aux dates, comme la projection : une vente datée avant son achat passe.

`AccountType` ne bloque aucune écriture — les règles d'enveloppe sont déclaratives. Un achat de crypto dans un PEA se saisit, et un test le fige.
