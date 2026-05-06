<?php

namespace App\Infrastructure\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Widgets\ChartWidget as BaseChartWidget;

abstract class ChartWidget extends BaseChartWidget implements HasActions
{
    use InteractsWithActions;

    protected bool $isCollapsible = true;

    protected bool $isCollapsed = true;

    protected string $view = 'filament.widgets.collapsible-chart-widget';

    public bool $bareView = false;

    public function booted(): void
    {
        if ($this->bareView) {
            $this->view = 'filament.widgets.bare-chart-widget';
        }
    }

    /**
     * @return array<Action>
     */
    public function getHeaderActions(): array
    {
        return [];
    }
}
