<?php

namespace App\Domains\Analytics\Filament\Widgets\Dashboard;

use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Filament\Concerns\ComputesCorrelationMatrix;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class DashboardCorrelationMatrixWidget extends Widget implements HasActions, HasSchemas
{
    use ComputesCorrelationMatrix;

    protected string $view = 'filament.widgets.correlation-matrix-widget';

    protected function resolveCorrelationSecurities(): Collection
    {
        $securities = app(DashboardDataProvider::class)->allSecurities();

        if ($this->shownSecurityIds !== null) {
            $securities = $securities->whereIn('id', $this->shownSecurityIds);
        }

        if ($securities->isEmpty()) {
            return Security::query()->where('id', null)->get();
        }

        return $securities;
    }
}
