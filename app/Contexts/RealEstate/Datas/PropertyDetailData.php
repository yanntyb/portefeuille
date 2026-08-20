<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Fiche complète d'un bien : identité, indicateurs, cash-flow, historique des loyers et des charges, prêt. */
readonly class PropertyDetailData implements JsonSerializable
{
    /**
     * @param  list<MonthlyCashFlowData>  $monthlyCashFlows  12 derniers mois.
     * @param  list<RentMonthData>  $rentHistory  Plus récent d'abord.
     * @param  list<ExpenseYearData>  $expenseYears  Plus récent d'abord.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $address,
        public string $acquisitionDate,
        public float $acquisitionPrice,
        public float $acquisitionFees,
        public float $currentValue,
        public float $netWorth,
        public PropertyMetricsData $metrics,
        public array $monthlyCashFlows,
        public array $rentHistory,
        public array $expenseYears,
        public ?LoanSummaryData $loan,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'acquisitionDate' => $this->acquisitionDate,
            'acquisitionPrice' => $this->acquisitionPrice,
            'acquisitionFees' => $this->acquisitionFees,
            'currentValue' => $this->currentValue,
            'netWorth' => $this->netWorth,
            'metrics' => $this->metrics,
            'monthlyCashFlows' => array_map(fn (MonthlyCashFlowData $flow): array => $flow->jsonSerialize(), $this->monthlyCashFlows),
            'rentHistory' => array_map(fn (RentMonthData $month): array => $month->jsonSerialize(), $this->rentHistory),
            'expenseYears' => array_map(fn (ExpenseYearData $year): array => $year->jsonSerialize(), $this->expenseYears),
            'loan' => $this->loan,
        ];
    }
}
