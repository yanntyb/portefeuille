<?php

namespace App\Contexts\Market\Datas;

readonly class DividendSyncReportData
{
    /**
     * @param  array<string, int>  $synced  nombre de détachements écrits, indexé par ticker
     * @param  array<int, string>  $failed  tickers pour lesquels le fournisseur est resté muet
     * @param  string|null  $error  erreur du fournisseur derrière un échec total, sinon null
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
