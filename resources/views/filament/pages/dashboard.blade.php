<x-filament-panels::page>
    {{ $this->content }}

    <div wire:init="loadPrices"></div>
</x-filament-panels::page>
