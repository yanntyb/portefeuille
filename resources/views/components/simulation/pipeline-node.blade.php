@props(['object', 'index'])

@php
    $isEditing = $this->editingIndex === $index;
@endphp

<div
    wire:key="pipe-{{ $index }}"
    class="relative rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm group transition {{ $isEditing ? 'ring-2 ring-primary-500' : 'hover:ring-2 hover:ring-primary-500 cursor-pointer' }}"
>
    @if ($isEditing)
        <div class="px-4 py-3 sm:px-5 sm:py-4">
            <div class="flex justify-end mb-3">
                <button
                    wire:click="closeEdit"
                    type="button"
                    class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                >
                    Fermer
                </button>
            </div>

            {{ $this->editingSchema }}
        </div>
    @else
        <div
            wire:click="mountAction('objectMenu', { index: {{ $index }} })"
            class="flex items-center justify-between gap-6 px-4 py-3 sm:px-5 sm:py-4"
        >
            <div class="flex flex-col gap-2 min-w-0 w-full">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400 truncate">
                        {{ $this->formatDisplayName($object['nom'], $object['pipeline'] ?? null) }}
                    </span>
                    <span class="text-sm font-bold text-gray-900 dark:text-white shrink-0">
                        {{ $object['value'] ?: '—' }}
                    </span>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap" wire:click.stop>
                    @foreach ($object['steps'] as $step)
                        @if ($step['type'] === 'reference')
                            <x-filament::button
                                size="xs"
                                color="warning"
                                outlined
                                wire:click="mountAction('editReferencedParam', { paramName: '{{ $step['label'] }}' })"
                            >
                                {{ $this->formatDisplayName($step['label'], $object['pipeline'] ?? null) }}
                            </x-filament::button>
                        @elseif ($step['type'] === 'operator')
                            <span class="text-sm font-mono font-bold text-gray-400 dark:text-gray-500">
                                {{ $step['label'] }}
                            </span>
                        @elseif ($step['type'] === 'value')
                            <x-filament::badge size="lg" color="info">
                                {{ $step['label'] }}
                            </x-filament::badge>
                        @elseif ($step['type'] === 'function')
                            <x-filament::badge size="lg" color="purple" icon="heroicon-o-cog-6-tooth">
                                {{ $step['label'] }}
                            </x-filament::badge>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
