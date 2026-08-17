<?php

namespace App\Contexts\Valuation;

use App\Contexts\Valuation\Infrastructure\MemoizedPriceHistory;
use App\Contexts\Valuation\Infrastructure\MemoizedTransactionHistory;
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
        /**
         * Les deux lectures d'historique sont mémoïsées le temps d'une requête : les propriétés
         * différées du tableau de bord les redemandent à l'identique. `scoped()` et non
         * `singleton()`, sous peine de garder l'historique d'une requête pour la suivante.
         */
        $app->scoped(
            TransactionHistoryPort::class,
            fn (Application $app): TransactionHistoryPort => new MemoizedTransactionHistory($app->make($transactionHistory)),
        );
        $app->scoped(
            PriceHistoryPort::class,
            fn (Application $app): PriceHistoryPort => new MemoizedPriceHistory($app->make($priceHistory)),
        );
        $app->bind(InstrumentDirectoryPort::class, $instrumentDirectory);
    }
}
