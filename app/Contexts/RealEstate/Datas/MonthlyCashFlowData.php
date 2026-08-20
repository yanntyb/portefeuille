<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Un mois de trésorerie d'un bien : loyers encaissés, charges et échéance de prêt, net résultant. */
readonly class MonthlyCashFlowData implements JsonSerializable
{
    public function __construct(
        public string $month,
        public float $rents,
        public float $expenses,
        public float $loanPayment,
        public float $net,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'month' => $this->month,
            'rents' => $this->rents,
            'expenses' => $this->expenses,
            'loanPayment' => $this->loanPayment,
            'net' => $this->net,
        ];
    }
}
