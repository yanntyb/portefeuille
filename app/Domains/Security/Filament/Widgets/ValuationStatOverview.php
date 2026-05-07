<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use App\Infrastructure\Filament\Concerns\HasStatWidgetListeners;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;

class ValuationStatOverview extends Widget
{
    use HasReactiveTableProperties;
    use HasStatWidgetListeners;

    protected string $view = 'filament.widgets.valuation-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

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
