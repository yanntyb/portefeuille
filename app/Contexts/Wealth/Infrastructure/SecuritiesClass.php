<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\InstrumentType;

/** Les titres : actions, ETF, obligations, matières premières — et leurs dividendes. */
class SecuritiesClass extends PortfolioAssetClass
{
    public function key(): string
    {
        return 'securities';
    }

    public function label(): string
    {
        return 'Actions';
    }

    public function href(): string
    {
        return '/instruments';
    }

    public function incomeLabel(): ?string
    {
        return 'Dividendes';
    }

    /** @return ?list<InstrumentType> */
    protected function types(): ?array
    {
        return InstrumentType::securities();
    }

    protected function incomeSource(): ?IncomeSource
    {
        return IncomeSource::Dividend;
    }
}
