<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesSingleSecurityStats;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityGainStatsOverview extends Widget
{
    use ComputesSingleSecurityStats;

    protected string $view = 'filament.widgets.gain-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public ?int $walletId = null;

    /**
     * @return array{
     *     pru: string,
     *     currentPrice: string,
     *     priceDate: ?string,
     *     plusValue: string,
     *     plusValuePercentage: string,
     *     plusValuePositive: bool,
     *     realizedGain: string,
     *     realizedGainPositive: bool,
     *     fees: string,
     *     feesPercentage: string,
     * }
     */
    public function getGainData(): array
    {
        if (! $this->record) {
            return [
                'pru' => Number::currency(0, 'EUR'),
                'currentPrice' => Number::currency(0, 'EUR'),
                'priceDate' => null,
                'plusValue' => Number::currency(0, 'EUR'),
                'plusValuePercentage' => '0 %',
                'plusValuePositive' => true,
                'realizedGain' => Number::currency(0, 'EUR'),
                'realizedGainPositive' => true,
                'fees' => Number::currency(0, 'EUR'),
                'feesPercentage' => '0 %',
            ];
        }

        $stats = $this->computeStats();

        return [
            'pru' => Number::currency($stats['pru'], 'EUR'),
            'currentPrice' => $stats['close'] !== null ? Number::currency($stats['close'], 'EUR') : '-',
            'priceDate' => $stats['priceDate'],
            'plusValue' => Number::currency($stats['plusValue'], 'EUR'),
            'plusValuePercentage' => Number::format($stats['plusValuePercentage'], 2).' %',
            'plusValuePositive' => $stats['plusValue'] >= 0,
            'realizedGain' => Number::currency($stats['totalRealizedGain'], 'EUR'),
            'realizedGainPositive' => $stats['totalRealizedGain'] >= 0,
            'fees' => Number::currency($stats['totalFees'], 'EUR'),
            'feesPercentage' => Number::format($stats['feesPercentage'], 2).' %',
        ];
    }
}
