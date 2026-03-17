<?php

namespace App\Filament\Widgets\Securities;

use App\Data\CorrelationResult;
use App\Enums\CorrelationPeriod;
use App\Filament\Widgets\Securities\Concerns\HasReactiveTableProperties;
use App\Models\Security;
use App\Services\CorrelationCalculator;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class CorrelationMatrixWidget extends Widget
{
    use HasReactiveTableProperties;

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
        if ($this->tablePageClass === null || $this->walletId === null) {
            return null;
        }

        $securityIds = $this->getPageTableQuery()
            ->reorder()
            ->pluck('securities.id')
            ->toArray();

        if ($this->shownSecurityIds !== null) {
            $securityIds = array_values(array_intersect($securityIds, $this->shownSecurityIds));
        }

        if (count($securityIds) < 2) {
            return null;
        }

        $securities = Security::query()
            ->whereIn('id', $securityIds)
            ->get();

        $correlationPeriod = CorrelationPeriod::tryFrom($this->period) ?? CorrelationPeriod::OneYear;

        return app(CorrelationCalculator::class)->compute($securities, $correlationPeriod);
    }
}
