<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\InstrumentProviderPort;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\PriceProviderPort;
use App\Contexts\Market\Ports\SectorProviderPort;
use App\Shared\Python\PythonProcessException;
use App\Shared\Python\PythonRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class YahooFinanceAdapter implements InstrumentProviderPort, PriceFeedPort, PriceProviderPort, SectorProviderPort
{
    public function __construct(
        private readonly PythonRunner $python,
    ) {}

    public function supports(InstrumentType $type): bool
    {
        return in_array($type, [InstrumentType::Stock, InstrumentType::ETF]);
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
            $result = $this->python->run(YahooScript::PricesBulk->path(), [
                'tickers' => array_map(
                    fn (PriceRequestData $request): array => $this->window(
                        $request->ticker,
                        $request->startDate,
                        $request->endDate,
                    ),
                    $requests,
                ),
            ]);
        } catch (PythonProcessException $exception) {
            throw PriceFeedException::fetchFailed($exception->getMessage());
        }

        if (! $result->ok()) {
            throw PriceFeedException::fetchFailed($result->error ?? 'unknown error');
        }

        $prices = [];

        foreach ($result->data ?? [] as $ticker => $rows) {
            $prices[$ticker] = array_map(
                fn (array $row): PriceData => PriceData::fromArray($row),
                $rows,
            );
        }

        return $prices;
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
