<?php

namespace Database\Seeders;

use App\Contexts\Market\Enums\InstrumentType;

class GoldDcaSeeder extends FixedDcaSeeder
{
    protected function ticker(): string
    {
        return '4GLD.DE';
    }

    protected function instrumentName(): string
    {
        return 'Or (Xetra-Gold)';
    }

    protected function instrumentType(): InstrumentType
    {
        return InstrumentType::Commodity;
    }

    protected function walletName(): string
    {
        return 'Portefeuille Or';
    }
}
