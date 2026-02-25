<?php

namespace App\Filament\Widgets\Securities;

use App\Enums\PerformancePeriod;
use App\Services\PortfolioPerformanceCalculator;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityPerformanceStatsOverview extends Widget
{
    protected string $view = 'filament.widgets.performance-stats-overview';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public ?string $accountType = null;

    /**
     * @return list<array{label: string, value: string, color: string}>
     */
    public function getPerformanceData(): array
    {
        if (! $this->record) {
            return [];
        }

        $returns = app(PortfolioPerformanceCalculator::class)
            ->computeReturnsForSecurity($this->record, $this->accountType);

        $stats = [];

        foreach (PerformancePeriod::cases() as $period) {
            $value = $returns[$period->value];

            if ($value === null) {
                $stats[] = [
                    'label' => $period->getLabel(),
                    'value' => '—',
                    'color' => 'gray',
                ];

                continue;
            }

            $formatted = ($value >= 0 ? '+' : '').Number::format($value, 2).' %';

            $stats[] = [
                'label' => $period->getLabel(),
                'value' => $formatted,
                'color' => $value >= 0 ? 'success' : 'danger',
            ];
        }

        return $stats;
    }
}
