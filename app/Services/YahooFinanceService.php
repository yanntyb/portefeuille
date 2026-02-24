<?php

namespace App\Services;

use App\Enums\Sector;
use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
use App\Support\PythonScriptCaller;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

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
     * @param  Collection<int, Security>  $securities
     */
    public function fetchAndStorePricesBulk(Collection $securities): int
    {
        $pythonBin = base_path('.venv/bin/python');
        $scriptPath = storage_path('python/scripts/fetch_prices.py');
        $endDate = new \DateTimeImmutable('now');

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

            $latestDate = $security->prices()->max('date');
            $startDate = $latestDate
                ? (new \DateTimeImmutable($latestDate))->modify('+1 day')
                : new \DateTimeImmutable('-5 years');

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

        $results = Process::concurrently(function (Pool $pool) use ($tasks, $pythonBin, $scriptPath, $endDate): void {
            foreach ($tasks as $index => $task) {
                $input = json_encode([
                    'ticker' => $task['security']->ticker,
                    'start_date' => $task['startDate'],
                    'end_date' => $endDate->format('Y-m-d'),
                ]);

                $pool->as((string) $index)
                    ->timeout(60)
                    ->env(['PYTHONUNBUFFERED' => '1'])
                    ->input($input)
                    ->command("{$pythonBin} {$scriptPath}");
            }
        });

        $totalInserted = 0;

        foreach ($tasks as $index => $task) {
            $result = $results[(string) $index];

            if (! $result->successful()) {
                Log::warning("Failed to fetch prices for {$task['security']->name}: {$result->errorOutput()}");

                continue;
            }

            $decoded = json_decode($result->output(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("Invalid JSON for {$task['security']->name}: ".json_last_error_msg());

                continue;
            }

            $historicalData = $decoded['data'] ?? [];

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
