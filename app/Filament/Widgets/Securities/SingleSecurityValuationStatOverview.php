<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesSingleSecurityStats;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityValuationStatOverview extends Widget
{
    use ComputesSingleSecurityStats;

    protected string $view = 'filament.widgets.valuation-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public ?int $walletId = null;

    /**
     * @return array{valuation: string, color: string}
     */
    public function getValuationData(): array
    {
        if (! $this->record) {
            return [
                'valuation' => Number::currency(0, 'EUR'),
                'color' => 'success',
            ];
        }

        $stats = $this->computeStats();

        return [
            'valuation' => Number::currency($stats['valuation'], 'EUR'),
            'color' => $stats['valuation'] >= $stats['totalInvested'] ? 'success' : 'danger',
        ];
    }
}
