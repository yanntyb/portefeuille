<?php

namespace App\Infrastructure\Filament\Concerns;

use App\Domains\Portfolio\Services\PortfolioPerformanceCalculator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

trait ComputesPerformanceStats
{
    protected ?string $pollingInterval = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
    }

    #[On('prices-updated')]
    public function refreshStats(): void
    {
        // Triggers re-render with fresh data
    }

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
