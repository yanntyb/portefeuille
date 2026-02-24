<?php

namespace App\Services;

use App\Enums\Sector;
use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
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

    /**
     * @throws TickerResolutionException
     */
    public function fetchAndStoreSectors(Security $security): int
    {
        if ($security->ticker === null) {
            $security->ticker = $this->resolveTickerFromIsin($security->isin, $security->name);
            $security->save();
        }

        $result = PythonScriptCaller::call('fetch_sectors.py', [
            'ticker' => $security->ticker,
        ], timeout: 30);

        $sectorsData = $result['data'] ?? [];

        if ($sectorsData === []) {
            return 0;
        }

        $sectorValues = array_column(Sector::cases(), 'value');

        $rows = [];
        foreach ($sectorsData as $key => $weight) {
            $sector = in_array($key, $sectorValues) ? $key : Sector::Other->value;

            $rows[] = [
                'security_id' => $security->id,
                'sector' => $sector,
                'weight' => $weight,
            ];
        }

        SecuritySector::upsert(
            $rows,
            ['security_id', 'sector'],
            ['weight'],
        );

        $sectorKeys = array_column($rows, 'sector');
        $security->sectors()
            ->whereNotIn('sector', $sectorKeys)
            ->delete();

        return count($rows);
    }
}
