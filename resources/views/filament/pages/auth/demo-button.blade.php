<div class="w-full space-y-4">
    <div class="flex items-center justify-center gap-3">
        <div class="h-px flex-1 bg-gray-700"></div>
        <span class="text-sm leading-none text-gray-400">ou</span>
        <div class="h-px flex-1 bg-gray-700"></div>
    </div>

    <x-filament::button
        color="gray"
        class="w-full"
        icon="heroicon-o-eye"
        wire:click="loginAsDemo"
        wire:loading.attr="disabled"
    >
        <span wire:loading.remove wire:target="loginAsDemo">Présentation</span>
        <span wire:loading wire:target="loginAsDemo">Chargement des données...</span>
    </x-filament::button>
</div>
