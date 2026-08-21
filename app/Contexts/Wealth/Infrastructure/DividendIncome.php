<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Wealth\Ports\IncomePort;

class DividendIncome implements IncomePort
{
    public function __construct(private GetIncomeSummary $summary) {}

    public function monthlyDividendsFor(int $userId): float
    {
        /**
         * Filtré sur les dividendes : `Income` agrège aussi `IncomeSource::Rent`, et les loyers
         * arrivent nets par `RealEstatePort`. Sans le filtre, ils seraient comptés deux fois.
         */
        $summary = ($this->summary)($userId, IncomeSource::Dividend);

        return round($summary->last12Months / 12, 2);
    }
}
