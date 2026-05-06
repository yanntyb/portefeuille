<?php

namespace App\Domains\Security\Services;

use App\Infrastructure\Support\PythonScriptCaller;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YahooFinanceClient
{
    /**
     * @return list<array{symbol: string, name: string, exchange: string, type: string}>
     */
    public function search(string $query, ?string $fallbackQuery = null): array
    {
        try {
            $result = PythonScriptCaller::call('search_ticker.py', [
                'query' => $query,
                'fallback_query' => $fallbackQuery,
            ]);
        } catch (RuntimeException $e) {
            Log::warning('YahooFinanceClient::search failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (($result['status'] ?? '') !== 'ok') {
            return [];
        }

        return $result['data'] ?? [];
    }

    /**
     * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
     */
    public function fetchPrices(string $ticker, string $startDate, string $endDate): array
    {
        try {
            $result = PythonScriptCaller::call('fetch_prices.py', [
                'ticker' => $ticker,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ], 60);
        } catch (RuntimeException $e) {
            Log::warning('YahooFinanceClient::fetchPrices failed', [
                'ticker' => $ticker,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (($result['status'] ?? '') !== 'ok') {
            return [];
        }

        return $result['data'] ?? [];
    }

    /**
     * @param  list<array{ticker: string, start_date: string, end_date: string}>  $tickers
     * @return array<string, list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>>
     */
    public function fetchPricesBulk(array $tickers): array
    {
        try {
            $result = PythonScriptCaller::call('fetch_prices_bulk.py', [
                'tickers' => $tickers,
            ], 120);
        } catch (RuntimeException $e) {
            Log::warning('YahooFinanceClient::fetchPricesBulk failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (($result['status'] ?? '') !== 'ok') {
            return [];
        }

        return $result['data'] ?? [];
    }

    /**
     * @return array<string, float>
     */
    public function fetchSectors(string $ticker): array
    {
        try {
            $result = PythonScriptCaller::call('fetch_sectors.py', [
                'ticker' => $ticker,
            ], 30);
        } catch (RuntimeException $e) {
            Log::warning('YahooFinanceClient::fetchSectors failed', [
                'ticker' => $ticker,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (($result['status'] ?? '') !== 'ok') {
            return [];
        }

        return $result['data'] ?? [];
    }
}
