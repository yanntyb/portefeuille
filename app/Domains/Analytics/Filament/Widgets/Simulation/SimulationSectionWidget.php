<?php

namespace App\Domains\Analytics\Filament\Widgets\Simulation;

use Filament\Widgets\Widget;

class SimulationSectionWidget extends Widget
{
    protected string $view = 'filament.widgets.simulation.simulation-section-widget';

    protected int|string|array $columnSpan = 'full';

    public float $capitalInitial = 10000;

    public float $versementMensuel = 500;

    public float $tauxMoyen = 7;

    public float $volatilite = 15;

    public int $nbSimulations = 500;
}
