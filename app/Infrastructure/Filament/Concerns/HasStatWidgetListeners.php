<?php

namespace App\Infrastructure\Filament\Concerns;

use Livewire\Attributes\On;

trait HasStatWidgetListeners
{
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
}
