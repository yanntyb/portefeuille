<?php

namespace App\Contexts\Market\Datas;

readonly class PriceSyncReportData
{
    /**
     * @param  array<string, int>  $synced  number of prices written, keyed by ticker
     * @param  array<int, string>  $failed  tickers the provider could not be reached for
     * @param  string|null  $error  provider error behind a total failure, null otherwise
     */
    public function __construct(
        public array $synced = [],
        public array $failed = [],
        public ?string $error = null,
    ) {}

    public function total(): int
    {
        return count($this->synced) + count($this->failed);
    }

    public function syncedCount(): int
    {
        return count($this->synced);
    }

    public function isTotalFailure(): bool
    {
        return $this->total() > 0 && $this->synced === [];
    }
}
