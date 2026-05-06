<x-filament-widgets::widget>
    @livewire(\App\Domains\Analytics\Filament\Widgets\Simulation\MonteCarloChartWidget::class, [
        'capitalInitial' => $this->capitalInitial,
        'versementMensuel' => $this->versementMensuel,
        'tauxMoyen' => $this->tauxMoyen,
        'volatilite' => $this->volatilite,
        'nbSimulations' => $this->nbSimulations,
    ], 'wallet-mc-chart')
</x-filament-widgets::widget>
