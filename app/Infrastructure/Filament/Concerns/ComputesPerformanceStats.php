<?php

namespace App\Infrastructure\Filament\Concerns;

use App\Domains\Portfolio\Services\PortfolioPerformanceCalculator;
use Illuminate\Database\Eloquent\Collection;

trait ComputesPerformanceStats
{
    protected ?string $pollingInterval = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    /**
     * @return array<int, array{label: string, value: string, color: string}>
     */
    public function getPerformanceData(): array
    {
        $securities = $this->resolvePerformanceSecurities();

        $calculator = app(PortfolioPerformanceCalculator::class);
        $returns = $calculator->computeReturns($securities);

        return PortfolioPerformanceCalculator::formatReturnsAsStats($returns);
    }

    /**
     * @return Collection<int, \App\Domains\Security\Models\Security>
     */
    abstract protected function resolvePerformanceSecurities(): Collection;
}
