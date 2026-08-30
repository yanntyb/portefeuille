<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Datas\WealthSeriesData;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;

/**
 * Part patrimoine de l'instantané hors-ligne : les quatre props du tableau de bord, y compris les
 * trois qu'il diffère. Les mêmes actions que le contrôleur, donc jamais une composition parallèle
 * qui pourrait diverger de ce que la page affiche.
 */
class BuildWealthSnapshot
{
    public function __construct(
        private GetWealthOverview $overview,
        private BuildWealthSeries $series,
        private GetWealthIncome $income,
        private GetWealthTransactions $transactions,
    ) {}

    /**
     * @return array{
     *     overview: WealthOverviewData,
     *     series: WealthSeriesData,
     *     income: WealthIncomeData,
     *     transactions: list<WealthTransactionLineData>,
     * }
     */
    public function __invoke(int $userId): array
    {
        return [
            'overview' => ($this->overview)($userId),
            'series' => ($this->series)($userId),
            'income' => ($this->income)($userId),
            'transactions' => ($this->transactions)($userId),
        ];
    }
}
