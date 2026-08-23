<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Datas\WealthSeriesData;

/**
 * Part patrimoine de l'instantané hors-ligne : les trois props du tableau de bord, y compris les
 * deux qu'il diffère. Les mêmes actions que le contrôleur, donc jamais une composition parallèle
 * qui pourrait diverger de ce que la page affiche.
 */
class BuildWealthSnapshot
{
    public function __construct(
        private GetWealthOverview $overview,
        private BuildWealthSeries $series,
        private GetWealthIncome $income,
    ) {}

    /**
     * @return array{
     *     overview: WealthOverviewData,
     *     series: WealthSeriesData,
     *     income: WealthIncomeData,
     * }
     */
    public function __invoke(int $userId): array
    {
        return [
            'overview' => ($this->overview)($userId),
            'series' => ($this->series)($userId),
            'income' => ($this->income)($userId),
        ];
    }
}
