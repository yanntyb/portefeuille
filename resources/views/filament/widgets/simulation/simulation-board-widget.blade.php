@php
    $layout = $this->getLayout();
    $simulationOptions = $this->getSimulationOptions();
@endphp

<x-filament-widgets::widget class="flex flex-col gap-6">
    {{-- Sélecteur de simulation --}}
    <div class="max-w-xs">
        {{ $this->simulationSelectorSchema }}
    </div>

    {{-- Zone 1 : Paramètres --}}
    <x-filament::section heading="Paramètres" description="Données d'entrée">
        <div class="flex flex-col gap-2">
            @foreach ($layout['params'] as $item)
                <x-simulation.param-card :object="$item['object']" :index="$item['index']" />
            @endforeach

            {{-- Ajouter --}}
            <div
                wire:click="addObject"
                class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center gap-2 px-4 py-3 text-gray-400 hover:text-primary-500 hover:border-primary-500 dark:hover:text-primary-400 dark:hover:border-primary-400 transition cursor-pointer"
            >
                <x-filament::icon icon="heroicon-o-plus" class="w-5 h-5" />
                <span class="text-sm">Ajouter un paramètre</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Zone 2 : Pipelines (tabs) --}}
    <x-filament::section heading="Pipelines" description="Transformations (pipe |>)">
        <x-filament::tabs>
            @foreach ($layout['pipelines'] as $name => $nodes)
                <x-filament::tabs.item
                    :active="($this->activePipeline ?? array_key_first($layout['pipelines'])) === $name"
                    wire:click="$set('activePipeline', '{{ $name }}')"
                >
                    {{ $name }}
                    @if (count($nodes) > 0)
                        <x-slot name="badge">{{ count($nodes) }}</x-slot>
                    @endif
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @php
            $activeName = $this->activePipeline ?? array_key_first($layout['pipelines']);
            $activeNodes = $layout['pipelines'][$activeName] ?? [];
        @endphp

        @php
            $activeParams = collect($activeNodes)->filter(fn ($item) => empty($item['object']['steps']));
            $activePipes = collect($activeNodes)->filter(fn ($item) => ! empty($item['object']['steps']));
        @endphp

        @if ($activeParams->isNotEmpty())
            <div class="flex flex-col gap-2 mt-4">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Paramètres</p>
                @foreach ($activeParams as $item)
                    <x-simulation.param-card :object="$item['object']" :index="$item['index']" />
                @endforeach
            </div>
        @endif

        <div class="flex flex-col gap-2 mt-4">
            @if ($activeParams->isNotEmpty())
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Transformations</p>
            @endif

            @forelse ($activePipes as $item)
                <x-simulation.pipeline-node :object="$item['object']" :index="$item['index']" />
            @empty
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">Aucune transformation dans {{ $activeName }}.</p>
            @endforelse

            {{-- Ajouter --}}
            <div
                wire:click="addObject('{{ $activeName }}')"
                class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center gap-2 px-4 py-3 text-gray-400 hover:text-primary-500 hover:border-primary-500 dark:hover:text-primary-400 dark:hover:border-primary-400 transition cursor-pointer"
            >
                <x-filament::icon icon="heroicon-o-plus" class="w-5 h-5" />
                <span class="text-sm">Ajouter une transformation</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Zone 3 : Scénarios --}}
    <x-filament::section heading="Scénarios" description="Comparaison multi-paramètres">
        {{ $this->table }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
