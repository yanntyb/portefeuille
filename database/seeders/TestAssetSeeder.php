<?php

namespace Database\Seeders;

use App\Domains\Asset\Models\Assets\Stock;
use Illuminate\Database\Seeder;

class TestAssetSeeder extends Seeder
{
    public function run(): void
    {
        Stock::firstOrCreate(
            ['isin' => 'US0378331005'],
            [
                'name' => 'Apple Inc.',
                'ticker' => 'AAPL',
            ],
        );

        Stock::firstOrCreate(
            ['isin' => 'US5949181045'],
            [
                'name' => 'Microsoft Corporation',
                'ticker' => 'MSFT',
            ],
        );

        Stock::firstOrCreate(
            ['isin' => 'IE00B0M63284'],
            [
                'name' => 'iShares Core S&P 500 ETF',
                'ticker' => 'IVV',
            ],
        );
    }
}
