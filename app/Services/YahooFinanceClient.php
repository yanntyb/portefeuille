<?php

namespace App\Services;

use App\Services\YahooFinance\Requests\GetChartRequest;
use App\Services\YahooFinance\Requests\GetQuoteSummaryRequest;
use App\Services\YahooFinance\Requests\SearchRequest;
use App\Services\YahooFinance\YahooFinanceConnector;

class YahooFinanceClient
{
    private const BULK_DELAY_MICROSECONDS = 2_000_000;

    public function __construct(
        private readonly YahooFinanceConnector $connector = new YahooFinanceConnector,
    ) {}

    /**
     * @return list<array{symbol: string, name: string, exchange: string, type: string}>
     */
    public function search(string $query): array
    {
        $response = $this->connector->send(new SearchRequest($query));

        if ($response->failed()) {
            return [];
        }

        $quotes = $response->json('quotes', []);

        return array_map(fn (array $q) => [
            'symbol' => $q['symbol'] ?? '',
            'name' => $q['longname'] ?? $q['shortname'] ?? '',
            'exchange' => $q['exchDisp'] ?? $q['exchange'] ?? '',
            'type' => $q['typeDisp'] ?? $q['quoteType'] ?? '',
        ], $quotes);
    }

    /**
     * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
     */
    public function fetchPrices(string $ticker, string $startDate, string $endDate): array
    {
        $response = $this->connector->send(new GetChartRequest($ticker, $startDate, $endDate));

        if ($response->failed()) {
            return [];
        }

        return $this->parseChartResponse($response->json());
    }

    /**
     * @param  list<array{ticker: string, start_date: string, end_date: string}>  $tickers
     * @return array<string, list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>>
     */
    public function fetchPricesBulk(array $tickers): array
    {
        $result = [];

        foreach ($tickers as $index => $info) {
            if ($index > 0) {
                usleep(self::BULK_DELAY_MICROSECONDS);
            }

            $prices = $this->fetchPrices($info['ticker'], $info['start_date'], $info['end_date']);
            if ($prices !== []) {
                $result[$info['ticker']] = $prices;
            }
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
    public function fetchSectors(string $ticker): array
    {
        $response = $this->connector->send(new GetQuoteSummaryRequest($ticker));

        if ($response->failed()) {
            return [];
        }

        $data = $response->json('quoteSummary.result.0', []);
        $sectors = $this->parseSectorWeightings($data);

        if ($sectors === []) {
            $sectors = $this->parseStockSector($data);
        }

        return $sectors;
    }

    /**
     * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
     */
    private function parseChartResponse(array $json): array
    {
        $result = $json['chart']['result'][0] ?? null;

        if ($result === null) {
            return [];
        }

        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];

        $data = [];
        foreach ($timestamps as $i => $ts) {
            $open = $quote['open'][$i] ?? null;
            $close = $quote['close'][$i] ?? null;

            if ($open === null || $close === null) {
                continue;
            }

            $data[] = [
                'date' => date('Y-m-d', $ts),
                'open' => round((float) $open, 4),
                'high' => round((float) ($quote['high'][$i] ?? 0), 4),
                'low' => round((float) ($quote['low'][$i] ?? 0), 4),
                'close' => round((float) $close, 4),
                'volume' => (int) ($quote['volume'][$i] ?? 0),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, float>
     */
    private function parseSectorWeightings(array $data): array
    {
        $sectorWeightings = $data['topHoldings']['sectorWeightings'] ?? [];
        $sectors = [];

        foreach ($sectorWeightings as $sectorData) {
            foreach ($sectorData as $key => $weightInfo) {
                $weight = is_array($weightInfo) ? ($weightInfo['raw'] ?? 0) : (float) $weightInfo;
                if ($weight > 0) {
                    $sectors[$this->normalizeKey($key)] = round((float) $weight, 6);
                }
            }
        }

        return $sectors;
    }

    /**
     * @return array<string, float>
     */
    private function parseStockSector(array $data): array
    {
        $sector = $data['assetProfile']['sector'] ?? null;

        if ($sector === null) {
            return [];
        }

        return [$this->normalizeKey($sector) => 1.0];
    }

    private function normalizeKey(string $key): string
    {
        $key = (string) preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $key);
        $key = str_replace(' ', '_', $key);

        return strtolower($key);
    }
}
