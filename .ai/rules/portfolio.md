---
paths:
  - 'app/Contexts/Portfolio/**'
---

# Portfolio

## HoldingValuator est le site unique du gain ; gainPct est null sur coût nul
`Portfolio\Services\HoldingValuator` est le seul endroit qui calcule la valorisation d'une position et son gain — à la ligne (`value()`) comme au total (`totals()`). `PortfolioView` et `Wealth` le demandent plutôt que de refaire la formule.

`gainPct` (donc `pct()`) rend `null`, jamais `0.0`, quand le coût est nul : un gain sans mise à laquelle le rapporter n'a pas de pourcentage, et « 0 % » mentirait. Vrai à la ligne comme au total.

`GetPortfolioPositions` et `GetPortfolioOverview` sont toutes deux liées en `scoped` dans `PortfolioProvider` et mémoïsent leurs lignes par utilisateur : une seule lecture du portefeuille sert tous les appelants d'une même requête HTTP. Un appel par position (une fiche par position détenue, par exemple) rouvrirait sinon un N+1 sur l'instantané.

## Les analyses lisent les positions, les listes lisent les lignes
`HoldingLineData` = une ligne par enveloppe ; `PositionLineData` = une position par actif, enveloppes confondues. La page liste affiche des lignes, la page analyse raisonne en positions. L'argument est la concentration : un titre à 30 % réparti sur deux comptes apparaîtrait comme deux lignes à 15 %, et le HHI le ferait passer pour une exposition modérée — l'enveloppe est un fait fiscal, pas un fait de marché. Conséquence assumée : un même titre apparaît une fois sur `/actions/analyse` et deux sur `/actions`, et tout rendu qui regroupe doit le dire. `GetPortfolioAnalysis::positionsOf()` refait ce regroupement au lieu de réutiliser `GetPortfolioPositions`, faute d'avoir étendu cette dernière avec un filtre par classe et le nom de l'actif — consolidation à trancher dans un chantier ultérieur, pas un correctif à faire au fil de l'eau.

## Les frais entrent une fois dans le coût, jamais deux
`CostBasis` ajoute les frais d'achat au coût : le PRU projeté sur une position est frais inclus, donc l'investi, le gain et le pourcentage de la fiche le sont aussi. `CalculateRealizedGain` n'en compte pas deux fois — les frais d'achat sont déjà dans le PRU, seuls ceux de la vente se soustraient encore.

`TransactionFlow` est le seul site du montant d'une ligne : achat majoré de ses frais, vente minorée des siens, toujours rendu positif. `Portfolio\Actions\GetTransactionJournal`, seul lecteur du journal, l'appelle au lieu de refaire `quantité × prix` ; le solde d'une année, somme des montants, en hérite côté front.

`TransactionFlow` est aussi le seul site du SIGNE d'un mouvement : `cashDelta()` rend l'effet de la ligne sur la trésorerie de l'enveloppe — un achat ou un retrait négatifs, une vente, un versement ou un dividende positifs. `GetCashMovements` et le contrôle de survente le lui demandent plutôt que de retester `TransactionType` au cas par cas.

`Valuation\Services\ValuationCalculator` tient son propre PRU pour ses ventes : les frais y entrent aussi, sans quoi son investi et son coût diraient deux montants différents.

## AccountType est le seul site des règles d'enveloppe
`Portfolio\Enums\AccountType` porte tout ce que dit une enveloppe de détention : libellé, régime d'imposition, maturité, expositions admises. Les règles sont déclaratives — affichées, jamais appliquées à un calcul. L'application n'estime aucun impôt.

Le plafond de versement en est délibérément absent, mais plus pour la raison d'origine : depuis le chantier liquidités, `Deposit` existe et `CashLedger::netContributions()` calcule un montant versé. Le plafond porte cependant sur les VERSEMENTS BRUTS CUMULÉS (chaque versement compte, jamais nettés par les retraits), alors que `netContributions` rend un flux net — retirer puis reverser la même somme ne libère pas de plafond dans la réalité, mais le ferait dans ce calcul. Cette lecture brute-vs-nette n'a pas été tranchée, donc le plafond reste hors périmètre : l'absence n'est plus faute de donnée, mais faute de décision.

Une position se lit par actif ET par enveloppe : `holdings_projection` a pour clé primaire `(asset_id, wallet_id)`, et `HoldingLineData` porte `walletId`. Tout regroupement côté front doit donc clé sur les deux — `instrumentList.ts` le faisait sur `assetId` seul et confondait les deux lignes d'un titre tenu dans deux comptes.

## GetPositionStock est le site unique du stock d'une position, et le gain réalisé se recalcule en grappe
`GetPositionStock` porte l'arithmétique « achats moins ventes », prix de revient compris. `ProjectHolding` la consomme, et le contrôle de survente de `TransactionRequest` aussi — avec son argument `$ignoringTransactionId`, sans lequel porter une vente de 4 à 5 se comparerait à un stock dont ses propres 4 titres sont déjà déduits.

`realized_gain` est une colonne STOCKÉE que `GetRealizedGains` relit telle quelle, et `CalculateRealizedGain` la dérive des achats antérieurs à la vente. Corriger ou supprimer un achat rend donc faux le gain de chaque vente postérieure : `RecomputeRealizedGains`, appelée par l'observateur, réécrit toutes les ventes de l'enveloppe — par `saveQuietly()`, sinon `updating` la rappellerait sans fin.

Survente refusée à la saisie, mais pour une raison précise : `ProjectHolding` SUPPRIME la ligne de position dès que la quantité tombe à zéro ou moins, si bien qu'une survente effacerait la position au lieu de la mettre en défaut. Le contrôle est volontairement aveugle aux dates, comme la projection : une vente datée avant son achat passe.

`AccountType` ne bloque aucune écriture — les règles d'enveloppe sont déclaratives. Un achat de crypto dans un PEA se saisit, et un test le fige.

## Les versements déduits se réécrivent en grappe, jamais à la main
`RecomputeCashDeposits`, appelée par l'observateur des transactions, écrit les lignes `Deposit` déduites (`auto = true`) : le versement manquant que `CashLedger::missingDeposits()` calcule pour qu'un achat reste finançable. Même raison que `RecomputeRealizedGains` : une ligne déduite est la conséquence d'un achat, donc tout achat corrigé, déplacé ou supprimé la rend fausse ; on efface toutes les lignes `auto` de l'enveloppe et on les rejoue depuis les seules lignes saisies (`auto = false`), plutôt que de corriger une ligne déduite en place.

Les lignes saisies (`auto = false`) ne sont jamais touchées par ce mécanisme : saisir après coup un vrai virement fait disparaître de lui-même le versement déduit qu'il couvrait, sans intervention manuelle.

Invariant qui gouverne tout le calcul : le solde d'espèces d'une enveloppe n'est négatif à aucune date de son historique. C'est ce que `missingDeposits()` garantit en déduisant des versements, jamais l'inverse (on ne réduit jamais un achat pour faire tenir un solde).
