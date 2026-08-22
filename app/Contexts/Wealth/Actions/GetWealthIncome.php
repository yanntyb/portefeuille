<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\IncomeOriginData;
use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;

/**
 * Ce que le patrimoine laisse chaque mois. Chaque classe d'actif dit ce qu'elle rapporte et sous
 * quel nom ; celles qui ne rapportent rien — la crypto — ne prennent pas de ligne.
 */
class GetWealthIncome
{
    public function __construct(private AssetClassRegistry $classes) {}

    public function __invoke(int $userId): WealthIncomeData
    {
        $origins = [];
        $total = 0.0;

        foreach ($this->classes->all() as $class) {
            $label = $class->incomeLabel();

            if ($label === null) {
                continue;
            }

            $amount = $class->monthlyIncomeFor($userId);
            $origins[] = new IncomeOriginData(label: $label, amount: round($amount, 2));
            $total += $amount;
        }

        return new WealthIncomeData(monthlyTotal: round($total, 2), origins: $origins);
    }
}
