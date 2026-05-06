<?php

namespace App\Filament\Widgets\Securities;

use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class ValuationStatOverview extends Widget
{
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.valuation-stats-overview';

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
     * @return array{valuation: string, color: string}
     */
    public function getValuationData(): array
    {
        if ($this->tablePageClass === null) {
            return [
                'valuation' => Number::currency(0, 'EUR'),
                'color' => 'success',
            ];
        }

        $query = $this->getPageTableQuery();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $records = $query->with('latestPrice')->get();

        $valuation = $records->sum(fn ($record) => $record->currentValuation());

        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));

        return [
            'valuation' => Number::currency($valuation, 'EUR'),
            'color' => $valuation >= $totalInvested ? 'success' : 'danger',
        ];
    }
}
