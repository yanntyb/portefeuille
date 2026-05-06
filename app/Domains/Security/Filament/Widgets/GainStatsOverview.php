<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Filament\Concerns\ComputesGainStats;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

class GainStatsOverview extends Widget
{
    use ComputesGainStats;
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.gain-stats-overview';

    public ?int $walletId = null;

    protected function resolveGainSecurities(): Collection
    {
        if ($this->tablePageClass === null) {
            return Security::query()->where('id', null)->get();
        }

        $query = $this->getPageTableQuery();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        return $query->with('latestPrice')->get();
    }

    protected function resolveVolatilityValue(): ?string
    {
        if ($this->walletId === null) {
            return null;
        }

        $wallet = Wallet::find($this->walletId);
        if ($wallet === null) {
            return null;
        }

        $shownIds = $this->shownSecurityIds && count($this->shownSecurityIds) > 0 ? $this->shownSecurityIds : null;
        $volatiliteValue = app(VolatilityCalculator::class)->forWallet($wallet, $shownIds);

        return Number::format($volatiliteValue, 2).' %';
    }
}
