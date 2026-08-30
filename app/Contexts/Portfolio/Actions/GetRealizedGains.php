<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

/**
 * Le gain déjà encaissé, actif par actif : la somme des `realized_gain` posés par
 * `TransactionObserver` sur chaque vente, cumulée depuis toujours.
 *
 * Il se lit sur les transactions et non sur les positions, à dessein : un actif entièrement
 * revendu n'a plus de ligne `Holding`, et son aller-retour disparaîtrait du bilan s'il fallait
 * une position pour le porter.
 */
class GetRealizedGains
{
    /**
     * Les ventes de chaque utilisateur, pour la durée de la requête. L'action est liée en
     * `scoped` : une lecture sert les quatre expositions d'une page, sur le modèle de
     * `GetPortfolioOverview`.
     *
     * @var array<int, list<array{assetId: int, assetClass: AssetClass, amount: float}>>
     */
    private array $salesByUser = [];

    /**
     * Le gain réalisé par actif, les actifs sans vente absents.
     *
     * @return array<int, float> assetId => gain réalisé
     */
    public function __invoke(int $userId): array
    {
        $gains = [];

        foreach ($this->readSales($userId) as $sale) {
            $gains[$sale['assetId']] = round(($gains[$sale['assetId']] ?? 0.0) + $sale['amount'], 2);
        }

        return $gains;
    }

    /**
     * Le gain réalisé d'une ou plusieurs expositions, ou du portefeuille entier sans `$classes`.
     *
     * @param  ?list<AssetClass>  $classes
     */
    public function totalFor(int $userId, ?array $classes): float
    {
        $kept = $classes === null
            ? null
            : array_flip(array_map(fn (AssetClass $class): string => $class->value, $classes));

        $total = 0.0;

        foreach ($this->readSales($userId) as $sale) {
            if ($kept === null || isset($kept[$sale['assetClass']->value])) {
                $total += $sale['amount'];
            }
        }

        return round($total, 2);
    }

    /**
     * Toutes les ventes de l'utilisateur, exposition comprise : le découpage par classe se fait
     * en mémoire sur ce résultat mémoïsé, comme celui des lignes du portefeuille.
     *
     * @return list<array{assetId: int, assetClass: AssetClass, amount: float}>
     */
    private function readSales(int $userId): array
    {
        /** Jointure et non chargement lié : une requête, l'exposition n'étant qu'une colonne. */
        return $this->salesByUser[$userId] ??= Transaction::query()
            ->join('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Sell)
            ->whereNotNull('transactions.realized_gain')
            ->get(['transactions.asset_id', 'transactions.realized_gain', 'assets.asset_class'])
            ->map(fn (Transaction $sale): array => [
                'assetId' => (int) $sale->asset_id,
                'assetClass' => AssetClass::from((string) $sale->getAttributes()['asset_class']),
                'amount' => (float) $sale->realized_gain,
            ])
            ->values()
            ->all();
    }
}
