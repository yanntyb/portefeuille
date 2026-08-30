<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\IncomeOriginData;
use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;

/**
 * Ce que le patrimoine laisse chaque mois. Chaque classe d'actif dit ce qu'elle rapporte et sous
 * quel nom ; celles qui ne rapportent rien ne prennent pas de ligne — ni la crypto, qui n'a pas
 * d'origine du tout, ni une origine restée à zéro ce mois-ci.
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

            $amount = round($class->monthlyIncomeFor($userId), 2);

            if ($amount === 0.0) {
                continue;
            }

            $origins[] = new IncomeOriginData(label: $label, amount: $amount);
            $total += $amount;
        }

        return new WealthIncomeData(monthlyTotal: round($total, 2), origins: $origins);
    }
}
