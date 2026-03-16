<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget as BaseChartWidget;

abstract class ChartWidget extends BaseChartWidget
{
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
}
