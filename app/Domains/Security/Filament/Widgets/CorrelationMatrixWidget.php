<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Domains\Analytics\Data\CorrelationResult;
use App\Domains\Analytics\Enums\CorrelationPeriod;
use App\Domains\Analytics\Services\CorrelationCalculator;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class CorrelationMatrixWidget extends Widget implements HasActions, HasSchemas
{
    use HasReactiveTableProperties;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.correlation-matrix-widget';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    public string $period = '1y';

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
    }

    #[On('prices-updated')]
    public function refreshStats(): void
    {
        // Triggers re-render with fresh data
    }

    /**
     * @return array<Action>
     */
    public function getHeaderActions(): array
    {
        return [$this->infoCorrelationAction()];
    }

    public function infoCorrelationAction(): Action
    {
        return Action::make('infoCorrelation')
            ->label('Informations')
            ->icon('heroicon-m-information-circle')
            ->iconButton()
            ->color('gray')
            ->modalHeading('Corrélation')
            ->modalSubmitAction(false)
            ->modalCancelAction(fn ($action) => $action->label('Fermer'))
            ->action(fn () => null)
            ->schema([
                Callout::make('La corrélation mesure à quel point deux titres évoluent ensemble.')
                    ->info()
                    ->description('Proche de 1 = même direction, proche de 0 = indépendants, négatif = directions opposées.'),
                Callout::make('Diversification')
                    ->success()
                    ->description('Une corrélation moyenne basse indique une bonne diversification de votre portefeuille. Combiner des actifs peu corrélés réduit le risque global sans sacrifier le rendement espéré.'),
            ]);
    }

    public function getCorrelationData(): ?CorrelationResult
    {
        if ($this->tablePageClass === null || $this->walletId === null) {
            return null;
        }

        $securityIds = $this->getPageTableQuery()
            ->reorder()
            ->pluck('securities.id')
            ->toArray();

        if ($this->shownSecurityIds !== null) {
            $securityIds = array_values(array_intersect($securityIds, $this->shownSecurityIds));
        }

        if (count($securityIds) < 2) {
            return null;
        }

        $securities = Security::query()
            ->whereIn('id', $securityIds)
            ->get();

        $correlationPeriod = CorrelationPeriod::tryFrom($this->period) ?? CorrelationPeriod::OneYear;

        return app(CorrelationCalculator::class)->compute($securities, $correlationPeriod);
    }
}
