<?php

namespace App\Contexts\Wealth\Ports;

interface IncomePort
{
    /**
     * Dividendes des douze derniers mois, mensualisés. Les loyers en sont exclus : ils arrivent
     * nets par `RealEstatePort`, et `Income` les compte bruts.
     */
    public function monthlyDividendsFor(int $userId): float;
}
