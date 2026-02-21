<?php

namespace App\Filament\Resources\CtoSecurities\Pages;

use App\Filament\Resources\CtoSecurities\CtoSecurityResource;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListCtoSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CtoSecurityResource::class;

    protected static ?string $title = 'CTO';

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
        ];
    }
}
