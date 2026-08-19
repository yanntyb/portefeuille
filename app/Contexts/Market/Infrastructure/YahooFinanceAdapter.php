<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedException;
use App\Contexts\Market\Ports\DividendFeedPort;
use App\Contexts\Market\Ports\InstrumentProviderPort;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\PriceProviderPort;
use App\Contexts\Market\Ports\SectorProviderPort;
use App\Shared\Python\PythonRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class YahooFinanceAdapter implements DividendFeedPort, InstrumentProviderPort, PriceFeedPort, PriceProviderPort, SectorProviderPort
{
    /**
     * The bulk fetch funnels the whole catalogue through a single Python process: one yfinance
     * import plus one HTTP round trip per date window. The 30 seconds of `config('python.timeout')`
     * are sized for a single ticker and would time out on a few dozen instruments.
     */
    private const BULK_TIMEOUT_SECONDS = 900;

    public function __construct(
        private readonly PythonRunner $python,
    ) {}

    public function supportsInstruments(InstrumentType $type): bool
    {
        return $this->covers($type);
    }

    public function supportsPrices(InstrumentType $type): bool
    {
        return $this->covers($type);
    }

    public function supportsPriceFeed(InstrumentType $type): bool
    {
        return $this->covers($type);
    }

    /**
     * Yahoo only breaks down sectors for a company or a fund holding companies.
     */
    public function supportsSectors(InstrumentType $type): bool
    {
        return in_array($type, [InstrumentType::Stock, InstrumentType::ETF]);
    }

    /**
     * Yahoo quotes stocks, ETFs, cryptocurrencies and commodities, but not bonds.
     */
    private function covers(InstrumentType $type): bool
    {
        return in_array($type, [
            InstrumentType::Stock,
            InstrumentType::ETF,
            InstrumentType::Crypto,
            InstrumentType::Commodity,
        ]);
    }

    public function getCurrentPrice(int $assetId): ?float
    {
        $asset = Instrument::query()->find($assetId);

        if (! $asset || ! $asset->ticker) {
            return null;
        }

        try {
            $result = $this->python->run(YahooScript::Prices->path(), $this->window(
                $asset->ticker,
                now()->subYear()->format('Y-m-d'),
                now()->format('Y-m-d'),
            ));

            if (! $result->ok() || empty($result->data)) {
                return null;
            }

            $prices = $result->data;

            return end($prices)['close'] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    public function getPriceHistory(int $assetId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $asset = Instrument::query()->find($assetId);

        if (! $asset || ! $asset->ticker) {
            return collect();
        }

        $startDate ??= now()->subYear()->format('Y-m-d');
        $endDate ??= now()->format('Y-m-d');

        try {
            $result = $this->python->run(
                YahooScript::Prices->path(),
                $this->window($asset->ticker, $startDate, $endDate),
            );

            if (! $result->ok()) {
                return collect();
            }

            return collect($result->data ?? []);
        } catch (\Exception) {
            return collect();
        }
    }

    public function findBySymbol(string $symbol, InstrumentType $type): ?InstrumentData
    {
        try {
            $search = $this->python->run(YahooScript::Search->path(), ['query' => $symbol]);

            if (! $search->ok() || empty($search->data)) {
                return null;
            }

            $hit = $search->data[0];

            return new InstrumentData(
                symbol: $hit['symbol'],
                name: $hit['name'],
                type: $type,
                exchange: $hit['exchange'] ?? null,
                sectors: $this->getSectorAllocations($symbol, $type),
            );
        } catch (\Exception) {
            return null;
        }
    }

    public function getSectorAllocations(string $symbol, InstrumentType $type): array
    {
        try {
            $result = $this->python->run(YahooScript::Sectors->path(), ['ticker' => $symbol]);

            if (! $result->ok() || empty($result->data)) {
                return [];
            }

            $allocations = [];
            foreach ($result->data as $key => $weight) {
                $sector = Sector::tryFrom($key);
                if ($sector !== null) {
                    $allocations[] = new SectorAllocationData($sector, (float) $weight);
                }
            }

            return $allocations;
        } catch (\Exception) {
            return [];
        }
    }

    public function fetchPrices(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        try {
            $result = $this->python->run(
                YahooScript::PricesBulk->path(),
                [
                    'tickers' => array_map(
                        fn (PriceRequestData $request): array => $this->window(
                            $request->ticker,
                            $request->startDate,
                            $request->endDate,
                        ),
                        $requests,
                    ),
                ],
                self::BULK_TIMEOUT_SECONDS,
            );

            if (! $result->ok()) {
                throw PriceFeedException::fetchFailed($result->error ?? 'unknown error');
            }

            $prices = [];

            foreach ($result->data ?? [] as $ticker => $rows) {
                $prices[(string) $ticker] = array_map(
                    fn (array $row): PriceData => PriceData::fromArray($row),
                    $rows,
                );
            }

            return $prices;
        } catch (PriceFeedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw PriceFeedException::fetchFailed($exception->getMessage(), $exception);
        }
    }

    /**
     * Yahoo ne publie de détachement que pour une entreprise ou un fonds qui en détient.
     */
    public function supportsDividendFeed(InstrumentType $type): bool
    {
        return in_array($type, [InstrumentType::Stock, InstrumentType::ETF]);
    }

    public function fetchDividends(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        try {
            $result = $this->python->run(
                YahooScript::DividendsBulk->path(),
                [
                    'tickers' => array_map(
                        fn (DividendRequestData $request): array => $this->window(
                            $request->ticker,
                            $request->startDate,
                            $request->endDate,
                        ),
                        $requests,
                    ),
                ],
                self::BULK_TIMEOUT_SECONDS,
            );

            if (! $result->ok()) {
                throw DividendFeedException::fetchFailed($result->error ?? 'unknown error');
            }

            $dividends = [];

            foreach ($result->data ?? [] as $ticker => $rows) {
                $dividends[(string) $ticker] = array_map(
                    fn (array $row): DividendData => DividendData::fromArray($row),
                    $rows,
                );
            }

            return $dividends;
        } catch (DividendFeedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw DividendFeedException::fetchFailed($exception->getMessage(), $exception);
        }
    }

    /**
     * Build the script parameters for an inclusive date window.
     *
     * yfinance excludes its `end` bound, the port contract includes it.
     *
     * @return array{ticker: string, start_date: string, end_date: string}
     */
    private function window(string $ticker, string $startDate, string $endDate): array
    {
        return [
            'ticker' => $ticker,
            'start_date' => $startDate,
            'end_date' => Carbon::parse($endDate)->addDay()->format('Y-m-d'),
        ];
    }
}
