<?php

namespace App\Domains\Portfolio\Filament\Widgets;

use App\Domains\Portfolio\Enums\CurrencyModificationUnit;
use App\Domains\Portfolio\Enums\FeeScope;
use App\Domains\Portfolio\Enums\FrequencyUnit;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Models\WalletFee;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class WalletFeesWidget extends Widget
{
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.wallet-fees-widget';

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
     * @return array{
     *     transactionFees: string,
     *     transactionFeesPercentage: string,
     *     annualFees: string,
     *     walletFees: list<array{name: string, formatted: string}>,
     * }
     */
    public function getFeesData(): array
    {
        if ($this->tablePageClass === null || $this->walletId === null) {
            return [
                'transactionFees' => Number::currency(0, 'EUR'),
                'transactionFeesPercentage' => '0 %',
                'annualFees' => Number::currency(0, 'EUR'),
                'walletFees' => [],
            ];
        }

        $query = $this->getPageTableQuery();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $records = $query->with('latestPrice')->get();

        $totalInvested = (float) $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $totalFees = (float) $records->sum(fn ($record) => (float) ($record->total_fees ?? 0));

        $totalValuation = (float) $records->sum(fn ($record) => $record->currentValuation());

        $totalRealizedGain = (float) $records->sum(fn ($record) => (float) ($record->total_realized_gain ?? 0));
        $unrealizedGain = $totalValuation - $totalInvested;

        $transactionFeesPercentage = $totalInvested > 0
            ? Number::format(($totalFees / $totalInvested) * 100, 2).' %'
            : '0 %';

        $wallet = Wallet::find($this->walletId);
        $fees = $wallet?->fees ?? collect();

        $annualFeesTotal = 0.0;
        $walletFeesFormatted = [];

        foreach ($fees as $fee) {
            /** @var WalletFee $fee */
            $annual = match ($fee->unit) {
                CurrencyModificationUnit::Percentage => ($fee->value / 100) * match ($fee->scope) {
                    FeeScope::UnrealizedGain => max(0, $unrealizedGain),
                    FeeScope::RealizedGain => max(0, $totalRealizedGain),
                    default => $totalValuation,
                },
                CurrencyModificationUnit::Currency => match ($fee->frequency) {
                    FrequencyUnit::Monthly => (float) $fee->value * 12,
                    FrequencyUnit::Quarterly => (float) $fee->value * 4,
                    default => (float) $fee->value,
                },
            };

            $annualFeesTotal += $annual;

            $walletFeesFormatted[] = [
                'name' => $fee->name,
                'formatted' => $fee->formattedValue(),
                'annual' => Number::currency($annual, 'EUR'),
            ];
        }

        return [
            'transactionFees' => Number::currency($totalFees, 'EUR'),
            'transactionFeesPercentage' => $transactionFeesPercentage,
            'annualFees' => Number::currency($annualFeesTotal, 'EUR'),
            'walletFees' => $walletFeesFormatted,
        ];
    }
}
