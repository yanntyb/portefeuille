<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Securities\Tables\SecuritiesTable;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Support\MarketCalendar;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DashboardSecuritiesTableWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $hiddenSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public function mount(): void
    {
        $this->computeSecurityVisibility();
    }

    private function computeSecurityVisibility(): void
    {
        $allIds = Security::query()
            ->whereHas('transactions', fn ($q) => $q->where('user_id', auth()->id()))
            ->pluck('id')
            ->all();

        $idsWithPrice = SecurityPrice::query()
            ->whereIn('security_id', $allIds)
            ->where('date', '>=', MarketCalendar::lastTradingDate()->toDateString())
            ->pluck('security_id')
            ->unique()
            ->all();

        $this->pricelessSecurityIds = array_values(array_diff($allIds, $idsWithPrice));
        $this->shownSecurityIds = array_values(array_diff($allIds, $this->hiddenSecurityIds));
    }

    public function toggleSecurity(int $id): void
    {
        if (in_array($id, $this->hiddenSecurityIds)) {
            $this->hiddenSecurityIds = array_values(array_diff($this->hiddenSecurityIds, [$id]));
        } else {
            $this->hiddenSecurityIds[] = $id;
        }

        $this->computeSecurityVisibility();
        $this->dispatch('security-visibility-changed', shownSecurityIds: $this->shownSecurityIds);
    }

    public function table(Table $table): Table
    {
        return SecuritiesTable::configure(
            $table
                ->heading(null)
                ->query(fn (): Builder => Security::query()->forAuth())
        );
    }
}
