<?php

namespace App\Filament\Widgets\Dashboard;

use App\Data\CorrelationResult;
use App\Enums\CorrelationPeriod;
use App\Services\CorrelationCalculator;
use App\Services\DashboardDataProvider;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class DashboardCorrelationMatrixWidget extends Widget
{
    protected string $view = 'filament.widgets.correlation-matrix-widget';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    public string $period = '1y';

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

    public function getCorrelationData(): ?CorrelationResult
    {
        $securities = app(DashboardDataProvider::class)->allSecurities();

        if ($this->shownSecurityIds !== null) {
            $securities = $securities->whereIn('id', $this->shownSecurityIds);
        }

        if ($securities->count() < 2) {
            return null;
        }

        $correlationPeriod = CorrelationPeriod::tryFrom($this->period) ?? CorrelationPeriod::OneYear;

        return app(CorrelationCalculator::class)->compute($securities->values(), $correlationPeriod);
    }
}
