<?php

namespace App\Infrastructure\Filament\Concerns;

use App\Domains\Analytics\Data\CorrelationResult;
use App\Domains\Analytics\Enums\CorrelationPeriod;
use App\Domains\Analytics\Services\CorrelationCalculator;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

trait ComputesCorrelationMatrix
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected ?string $pollingInterval = null;

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
        $securities = $this->resolveCorrelationSecurities();

        if ($securities->count() < 2) {
            return null;
        }

        $correlationPeriod = CorrelationPeriod::tryFrom($this->period) ?? CorrelationPeriod::OneYear;

        return app(CorrelationCalculator::class)->compute($securities->values(), $correlationPeriod);
    }

    /**
     * @return Collection<int, \App\Domains\Security\Models\Security>
     */
    abstract protected function resolveCorrelationSecurities(): Collection;
}
