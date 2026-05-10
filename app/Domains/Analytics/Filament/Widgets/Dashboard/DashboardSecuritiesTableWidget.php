<?php

namespace App\Domains\Analytics\Filament\Widgets\Dashboard;

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Portfolio\Services\AssetQueryService;
use App\Infrastructure\Support\MarketCalendar;
use Filament\Tables\Columns\TextColumn;
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
        $allIds = Asset::query()
            ->whereHas('transactions', fn ($q) => $q->where('user_id', auth()->id()))
            ->pluck('id')
            ->all();

        $idsWithPrice = AssetPrice::query()
            ->whereIn('asset_id', $allIds)
            ->where('date', '>=', MarketCalendar::lastTradingDate()->toDateString())
            ->pluck('asset_id')
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
        return $table
            ->heading(null)
            ->query(fn (): Builder => app(AssetQueryService::class)->forAuthenticatedUser())
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
            ]);
    }
}
