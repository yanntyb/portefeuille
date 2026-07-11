<?php

namespace App\Contexts\Valuation;

use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ValuationProvider extends ServiceProvider
{
    /**
     * @param  class-string<TransactionHistoryPort>  $transactionHistory
     * @param  class-string<PriceHistoryPort>  $priceHistory
     * @param  class-string<InstrumentDirectoryPort>  $instrumentDirectory
     */
    public static function registers(
        Application $app,
        string $transactionHistory,
        string $priceHistory,
        string $instrumentDirectory,
    ): void {
        $app->bind(TransactionHistoryPort::class, $transactionHistory);
        $app->bind(PriceHistoryPort::class, $priceHistory);
        $app->bind(InstrumentDirectoryPort::class, $instrumentDirectory);
    }
}
