<?php

namespace App\Services;

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use DateTimeInterface;
use Scheb\YahooFinanceApi\ApiClient;

class YahooFinanceService
{
    public function __construct(private readonly ApiClient $apiClient) {}

    /**
     * @throws TickerResolutionException
     */
    public function resolveTickerFromIsin(string $isin): string
    {
        $results = $this->apiClient->search($isin);

        if ($results === []) {
            throw TickerResolutionException::noResultForIsin($isin);
        }

        return $results[0]->getSymbol();
    }

    /**
     * @throws TickerResolutionException
     */
    public function fetchAndStorePrices(Security $security, ?DateTimeInterface $startDate = null): int
    {
        if ($security->ticker === null) {
            $security->ticker = $this->resolveTickerFromIsin($security->isin);
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

        $historicalData = $this->apiClient->getHistoricalQuoteData(
            $security->ticker,
            ApiClient::INTERVAL_1_DAY,
            $startDate,
            $endDate,
        );

        if ($historicalData === []) {
            return 0;
        }

        $rows = array_map(fn ($data) => [
            'security_id' => $security->id,
            'date' => $data->getDate()->format('Y-m-d'),
            'open' => $data->getOpen(),
            'high' => $data->getHigh(),
            'low' => $data->getLow(),
            'close' => $data->getClose(),
            'volume' => $data->getVolume(),
        ], $historicalData);

        SecurityPrice::upsert(
            $rows,
            ['security_id', 'date'],
            ['open', 'high', 'low', 'close', 'volume'],
        );

        return count($rows);
    }
}
