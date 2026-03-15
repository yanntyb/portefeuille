<?php

namespace App\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

class DebugButton extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function debugAction(): Action
    {
        return Action::make('debug')
            ->label('Debug')
            ->icon('heroicon-o-bug-ant')
            ->link()
            ->schema([])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.debug-button');
    }
}
