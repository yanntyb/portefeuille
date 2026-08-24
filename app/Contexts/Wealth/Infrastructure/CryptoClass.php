<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Market\Enums\AssetClass;

/** La crypto, tenue comme un titre mais comptée à part : elle ne verse rien, elle n'a pas de ligne de revenu. */
class CryptoClass extends PortfolioAssetClass
{
    public function key(): string
    {
        return 'crypto';
    }

    public function label(): string
    {
        return 'Crypto';
    }

    public function href(): string
    {
        return '/crypto';
    }

    public function incomeLabel(): ?string
    {
        return null;
    }

    /** @return ?list<AssetClass> */
    protected function classes(): ?array
    {
        return [AssetClass::Crypto];
    }
}
