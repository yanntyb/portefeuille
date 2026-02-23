<?php

namespace App\Services;

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Support\PythonScriptCaller;
use DateTimeInterface;

class YahooFinanceService
{
    /**
     * @throws TickerResolutionException
     */
    public function resolveTickerFromIsin(string $isin, ?string $name = null): string
    {
        $results = $this->searchTicker($isin, $name);

        if ($results === []) {
            throw TickerResolutionException::noResultForIsin($isin);
        }

        return $results[0]['symbol'];
    }

    /**
     * @return list<array{symbol: string, name: string, exchange: string, type: string}>
     */
    public function searchTicker(string $query, ?string $fallbackQuery = null): array
    {
        $input = ['query' => $query];

        if ($fallbackQuery !== null) {
            $input['fallback_query'] = $fallbackQuery;
        }

        $result = PythonScriptCaller::call('search_ticker.py', $input);

        return $result['data'] ?? [];
    }

    /**
     * @throws TickerResolutionException
     */
    public function fetchAndStorePrices(Security $security, ?DateTimeInterface $startDate = null): int
    {
        if ($security->ticker === null) {
            $security->ticker = $this->resolveTickerFromIsin($security->isin, $security->name);
            $security->save();
        }

        if ($startDate === null) {
            $latestDate = $security->prices()->max('date');
            $startDate = $latestDate
                ? (new \DateTimeImmutable($latestDate))->modify('+1 day')
                : new \DateTimeImmutable('-5 years');
        }

        $endDate = new \DateTimeImmutable('now');

        if ($startDate > $endDate) {
            return 0;
        }

        $result = PythonScriptCaller::call('fetch_prices.py', [
            'ticker' => $security->ticker,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ], timeout: 60);

        $historicalData = $result['data'] ?? [];

        if ($historicalData === []) {
            return 0;
        }

        $rows = array_map(fn (array $data) => [
            'security_id' => $security->id,
            'date' => $data['date'],
            'open' => $data['open'],
            'high' => $data['high'],
            'low' => $data['low'],
            'close' => $data['close'],
            'volume' => $data['volume'],
        ], $historicalData);

        SecurityPrice::upsert(
            $rows,
            ['security_id', 'date'],
            ['open', 'high', 'low', 'close', 'volume'],
        );

        return count($rows);
    }
}
