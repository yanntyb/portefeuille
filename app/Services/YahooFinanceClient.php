<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YahooFinanceClient
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    private ?CookieJar $cookieJar = null;

    private ?string $crumb = null;

    private function authenticate(): void
    {
        if ($this->crumb !== null) {
            return;
        }

        $this->cookieJar = new CookieJar;

        Http::withOptions(['cookies' => $this->cookieJar])
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://fc.yahoo.com/v1/test/0.0.0.0');

        $response = Http::withOptions(['cookies' => $this->cookieJar])
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get('https://query2.finance.yahoo.com/v1/test/getcrumb');

        if (! $response->successful()) {
            throw new RuntimeException('Failed to obtain Yahoo Finance crumb token');
        }

        $this->crumb = $response->body();
    }

    private function request(): PendingRequest
    {
        $this->authenticate();

        return Http::withOptions(['cookies' => $this->cookieJar])
            ->withHeaders(['User-Agent' => self::USER_AGENT]);
    }

    /**
     * @return list<array{symbol: string, name: string, exchange: string, type: string}>
     */
    public function search(string $query): array
    {
        $response = $this->request()->get('https://query2.finance.yahoo.com/v1/finance/search', [
            'q' => $query,
            'quotesCount' => 10,
            'newsCount' => 0,
            'enableFuzzyQuery' => true,
            'crumb' => $this->crumb,
        ]);

        if (! $response->successful()) {
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
        $response = $this->request()
            ->timeout(60)
            ->get('https://query1.finance.yahoo.com/v8/finance/chart/'.urlencode($ticker), [
                'period1' => strtotime($startDate),
                'period2' => strtotime($endDate),
                'interval' => '1d',
                'crumb' => $this->crumb,
            ]);

        if (! $response->successful()) {
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

        foreach ($tickers as $info) {
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
        $response = $this->request()
            ->timeout(30)
            ->get('https://query1.finance.yahoo.com/v10/finance/quoteSummary/'.urlencode($ticker), [
                'modules' => 'topHoldings,assetProfile',
                'crumb' => $this->crumb,
            ]);

        if (! $response->successful()) {
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
