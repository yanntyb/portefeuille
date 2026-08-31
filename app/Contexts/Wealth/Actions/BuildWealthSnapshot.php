<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Datas\WealthSectorData;
use App\Contexts\Wealth\Datas\WealthSeriesData;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;

/**
 * Part patrimoine de l'instantané hors-ligne : les six props du tableau de bord, y compris les
 * cinq qu'il diffère. Les mêmes actions que le contrôleur, donc jamais une composition parallèle
 * qui pourrait diverger de ce que la page affiche.
 */
class BuildWealthSnapshot
{
    public function __construct(
        private GetWealthOverview $overview,
        private BuildWealthSeries $series,
        private GetWealthIncome $income,
        private GetWealthSectors $sectors,
        private GetWealthTransactions $transactions,
        private GetWealthAccounts $accounts,
    ) {}

    /**
     * @return array{
     *     overview: WealthOverviewData,
     *     series: WealthSeriesData,
     *     income: WealthIncomeData,
     *     sectors: list<WealthSectorData>,
     *     transactions: list<WealthTransactionLineData>,
     *     accounts: list<WealthAccountData>,
     * }
     */
    public function __invoke(int $userId): array
    {
        return [
            'overview' => ($this->overview)($userId),
            'series' => ($this->series)($userId),
            'income' => ($this->income)($userId),
            'sectors' => ($this->sectors)($userId),
            'transactions' => ($this->transactions)($userId),
            'accounts' => ($this->accounts)($userId),
        ];
    }
}
