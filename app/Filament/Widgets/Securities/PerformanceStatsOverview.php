<?php

namespace App\Filament\Widgets\Securities;

use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use App\Services\PortfolioPerformanceCalculator;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class PerformanceStatsOverview extends Widget
{
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.performance-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

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
     * @return list<array{label: string, value: string, color: string}>
     */
    public function getPerformanceData(): array
    {
        if ($this->tablePageClass === null) {
            return [];
        }

        $query = $this->getPageTableQuery();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $calculator = app(PortfolioPerformanceCalculator::class);

        return PortfolioPerformanceCalculator::formatReturnsAsStats(
            $calculator->computeReturns($query->with('latestPrice')->get()),
        );
    }
}
