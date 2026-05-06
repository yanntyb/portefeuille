<?php

namespace App\Infrastructure\Filament\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

trait ComputesGainStats
{
    protected ?string $pollingInterval = null;

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
     *     plusValue: string,
     *     plusValuePercentage: string,
     *     plusValuePositive: bool,
     *     realizedGain: string,
     *     realizedGainPositive: bool,
     *     fees: string,
     *     feesPercentage: string,
     *     volatilite: ?string,
     * }
     */
    public function getGainData(): array
    {
        $securities = $this->resolveGainSecurities();

        if ($securities->isEmpty()) {
            return [
                'plusValue' => Number::currency(0, 'EUR'),
                'plusValuePercentage' => '0 %',
                'plusValuePositive' => true,
                'realizedGain' => Number::currency(0, 'EUR'),
                'realizedGainPositive' => true,
                'fees' => Number::currency(0, 'EUR'),
                'feesPercentage' => '0 %',
                'volatilite' => null,
            ];
        }

        $totalInvested = $securities->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $totalFees = $securities->sum(fn ($record) => (float) ($record->total_fees ?? 0));
        $totalValuation = $securities->sum(fn ($record) => $record->currentValuation());
        $totalRealizedGain = $securities->sum(fn ($record) => (float) ($record->total_realized_gain ?? 0));

        $plusValue = $totalValuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        $volatilite = $this->resolveVolatilityValue();

        return [
            'plusValue' => Number::currency($plusValue, 'EUR'),
            'plusValuePercentage' => Number::format($plusValuePercentage, 2).' %',
            'plusValuePositive' => $plusValue >= 0,
            'realizedGain' => Number::currency($totalRealizedGain, 'EUR'),
            'realizedGainPositive' => $totalRealizedGain >= 0,
            'fees' => Number::currency($totalFees, 'EUR'),
            'feesPercentage' => Number::format($feesPercentage, 2).' %',
            'volatilite' => $volatilite,
        ];
    }

    /**
     * @return Collection<int, \App\Domains\Security\Models\Security>
     */
    abstract protected function resolveGainSecurities(): Collection;

    abstract protected function resolveVolatilityValue(): ?string;
}
