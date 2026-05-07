<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Infrastructure\Filament\Computations\ValuationComputation;
use App\Infrastructure\Filament\Concerns\ComputesSingleSecurityStats;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

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
            return ValuationComputation::computeFromStats([
                'valuation' => 0,
                'totalInvested' => 0,
            ]);
        }

        $stats = $this->computeStats();

        return ValuationComputation::computeFromStats($stats);
    }
}
