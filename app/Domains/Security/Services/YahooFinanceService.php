<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Contracts\SecurityPriceRepositoryInterface;
use App\Domains\Security\Enums\Sector;
use App\Domains\Security\Events\PriceUpdated;
use App\Domains\Security\Exceptions\TickerResolutionException;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\Security\Models\SecuritySector;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YahooFinanceService
{
    public function __construct(
        private YahooFinanceClient $client,
        private SecurityPriceRepositoryInterface $priceRepository,
    ) {}

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
        return $this->client->search($query, $fallbackQuery);
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

        $endDate = new \DateTimeImmutable('now');

        if ($startDate === null) {
            $latestDates = $this->priceRepository->getLatestDateForSecurities([$security->id]);
            $latestDate = $latestDates->get($security->id);
            $latestDateObj = $latestDate ? new \DateTimeImmutable($latestDate) : null;

            $earliestTransactionDate = DB::table('transactions')
                ->where('asset_id', $security->id)
                ->min('date');

            $earliestPriceDates = $this->priceRepository->getEarliestDateForSecurities([$security->id]);
            $earliestPriceDate = $earliestPriceDates->get($security->id);

            if ($latestDateObj !== null && $earliestTransactionDate !== null && $earliestTransactionDate < $earliestPriceDate) {
                $startDate = new \DateTimeImmutable($earliestTransactionDate);
            } else {
                $startDate = $latestDateObj === null
                    ? new \DateTimeImmutable('-5 years')
                    : ($latestDateObj->format('Y-m-d') === $endDate->format('Y-m-d')
                        ? $latestDateObj
                        : $latestDateObj->modify('+1 day'));
            }
        }

        if ($startDate > $endDate) {
            return 0;
        }

        $historicalData = $this->client->fetchPrices(
            $security->ticker,
            $startDate->format('Y-m-d'),
            $endDate->modify('+1 day')->format('Y-m-d'),
        );

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

        foreach ($rows as $row) {
            $price = $this->priceRepository->findBySecurityAndDate($row['security_id'], $row['date']);

            if ($price) {
                PriceUpdated::dispatch($price, $security);
            }
        }

        return count($rows);
    }

    /**
     * @param  Collection<int, Security>  $securities
     */
    public function fetchAndStorePricesBulk(Collection $securities, bool $force = false): int
    {
        $cacheKey = 'yahoo_prices_bulk_fetched:'.auth()->id();

        if (! $force && Cache::has($cacheKey)) {
            return 0;
        }

        $endDate = new \DateTimeImmutable('now');

        $securityIds = $securities->pluck('id')->all();

        $latestDates = $this->priceRepository->getLatestDateForSecurities($securityIds);

        $earliestTransactionDates = DB::table('transactions')
            ->selectRaw('asset_id, MIN(date) as earliest_date')
            ->whereIn('asset_id', $securityIds)
            ->groupBy('asset_id')
            ->pluck('earliest_date', 'asset_id');

        $earliestPriceDates = $this->priceRepository->getEarliestDateForSecurities($securityIds);

        /** @var array<int, array{security: Security, startDate: string}> */
        $tasks = [];

        foreach ($securities as $security) {
            if ($security->ticker === null) {
                try {
                    $security->ticker = $this->resolveTickerFromIsin($security->isin, $security->name);
                    $security->save();
                } catch (TickerResolutionException $e) {
                    Log::warning("Failed to resolve ticker for {$security->name}: {$e->getMessage()}");

                    continue;
                }
            }

            $latestDate = $latestDates->get($security->id);
            $latestDateObj = $latestDate ? new \DateTimeImmutable($latestDate) : null;

            $earliestTransaction = $earliestTransactionDates->get($security->id);
            $earliestPrice = $earliestPriceDates->get($security->id);

            if ($latestDateObj !== null && $earliestTransaction !== null && $earliestTransaction < $earliestPrice) {
                $startDate = new \DateTimeImmutable($earliestTransaction);
            } else {
                $startDate = $latestDateObj === null
                    ? new \DateTimeImmutable('-5 years')
                    : ($latestDateObj->format('Y-m-d') === $endDate->format('Y-m-d')
                        ? $latestDateObj
                        : $latestDateObj->modify('+1 day'));
            }

            if ($startDate > $endDate) {
                continue;
            }

            $tasks[] = [
                'security' => $security,
                'startDate' => $startDate->format('Y-m-d'),
            ];
        }

        if ($tasks === []) {
            return 0;
        }

        $tickersInput = array_map(fn (array $task) => [
            'ticker' => $task['security']->ticker,
            'start_date' => $task['startDate'],
            'end_date' => $endDate->modify('+1 day')->format('Y-m-d'),
        ], $tasks);

        $allData = $this->client->fetchPricesBulk($tickersInput);

        Cache::put($cacheKey, true, now()->addHour());

        $totalInserted = 0;

        foreach ($tasks as $task) {
            $ticker = $task['security']->ticker;
            $historicalData = $allData[$ticker] ?? [];

            if ($historicalData === []) {
                continue;
            }

            $rows = array_map(fn (array $data) => [
                'security_id' => $task['security']->id,
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

            foreach ($rows as $row) {
                $price = $this->priceRepository->findBySecurityAndDate($row['security_id'], $row['date']);

                if ($price) {
                    PriceUpdated::dispatch($price, $task['security']);
                }
            }

            $totalInserted += count($rows);
        }

        return $totalInserted;
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

        $sectorsData = $this->client->fetchSectors($security->ticker);

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
