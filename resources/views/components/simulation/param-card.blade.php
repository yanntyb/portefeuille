@props(['object', 'index'])

@php
    $isEditing = $this->editingIndex === $index;
@endphp

<div
    wire:key="param-{{ $index }}"
    class="relative w-full rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm group transition {{ $isEditing ? 'ring-2 ring-primary-500' : 'hover:ring-2 hover:ring-primary-500 cursor-pointer' }}"
>
    @if ($isEditing)
        {{-- Formulaire Filament inline --}}
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
        {{-- Affichage compact en ligne --}}
        <div
            wire:click="mountAction('objectMenu', { index: {{ $index }} })"
            class="flex items-center justify-between gap-6 px-4 py-3 sm:px-5 sm:py-4"
        >
            <span class="text-sm text-gray-500 dark:text-gray-400 truncate">
                {{ $this->formatDisplayName($object['nom'] ?: 'sans nom', $object['pipeline'] ?? null) }}
            </span>
            <span class="text-sm font-bold text-gray-900 dark:text-white shrink-0 ml-auto text-right">
                {{ $object['value'] ?: '—' }}
            </span>
        </div>
    @endif
</div>
