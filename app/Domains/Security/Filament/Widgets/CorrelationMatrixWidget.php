<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Domains\Security\Models\Security;
use App\Infrastructure\Filament\Concerns\ComputesCorrelationMatrix;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class CorrelationMatrixWidget extends Widget implements HasActions, HasSchemas
{
    use ComputesCorrelationMatrix;
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.correlation-matrix-widget';

    protected function resolveCorrelationSecurities(): Collection
    {
        if ($this->tablePageClass === null || $this->walletId === null) {
            return Security::query()->where('id', null)->get();
        }

        $securities = $this->getFilteredSecurities(withPrice: false, reorder: true);

        if ($securities->count() < 2) {
            return Security::query()->where('id', null)->get();
        }

        return $securities;
    }
}
