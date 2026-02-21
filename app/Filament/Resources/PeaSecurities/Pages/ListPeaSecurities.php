<?php

namespace App\Filament\Resources\PeaSecurities\Pages;

use App\Filament\Resources\PeaSecurities\PeaSecurityResource;
use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListPeaSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PeaSecurityResource::class;

    protected static ?string $title = 'PEA';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    public function mount(): void
    {
        parent::mount();

        $this->shownSecurityIds = static::getResource()::getEloquentQuery()
            ->pluck('securities.id')
            ->all();
    }

    public function toggleSecurity(int $id): void
    {
        if (in_array($id, $this->shownSecurityIds)) {
            $this->shownSecurityIds = array_values(array_diff($this->shownSecurityIds, [$id]));
        } else {
            $this->shownSecurityIds[] = $id;
        }

        $this->dispatch('security-visibility-changed', shownSecurityIds: $this->shownSecurityIds);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SecurityStatsOverview::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
            ValuationChartWidget::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
            AllocationChartWidget::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
        ];
    }
}
