<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;

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
        return '/'.AssetClass::Equity->slug();
    }

    public function incomeLabel(): ?string
    {
        return 'Dividendes';
    }

    /** @return ?list<AssetClass> */
    protected function classes(): ?array
    {
        return [AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity];
    }

    protected function incomeSource(): ?IncomeSource
    {
        return IncomeSource::Dividend;
    }
}
