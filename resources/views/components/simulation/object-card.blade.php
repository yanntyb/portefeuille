@props(['object', 'index'])

<div
    wire:key="object-{{ $index }}"
    wire:click="mountAction('editObject', { index: {{ $index }} })"
    class="relative w-48 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col items-center justify-center p-3 group cursor-pointer hover:ring-2 hover:ring-primary-500 transition"
>
    <button
        wire:click.stop="removeObject({{ $index }})"
        type="button"
        class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition text-gray-400 hover:text-danger-500 text-lg leading-none"
    >
        &times;
    </button>

    <span class="text-xs font-mono text-gray-500 dark:text-gray-400 mb-2 truncate max-w-full">
        {{ $object['nom'] ?: 'sans nom' }}
    </span>

    @if (! empty($object['source']))
        @php
            $sourceValue = $this->getSourceValue($object['source']);
        @endphp
        <div class="w-full flex items-center justify-center gap-1.5 mb-1">
            <div class="text-center">
                <span class="text-xs text-gray-400 dark:text-gray-500 block">entrée</span>
                <span class="text-sm font-semibold text-amber-600 dark:text-amber-400">{{ $sourceValue ?? '?' }}</span>
                <span class="text-[9px] font-mono text-gray-400 block">{{ $object['source'] }}</span>
            </div>
            <span class="text-gray-300 dark:text-gray-600 text-lg">&rarr;</span>
            <div class="text-center">
                <span class="text-xs text-gray-400 dark:text-gray-500 block">sortie</span>
                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ $object['value'] ?: '—' }}</span>
            </div>
        </div>
    @else
        <span class="text-xl font-bold text-gray-900 dark:text-white">
            {{ $object['value'] ?: '—' }}
        </span>
    @endif

    @if (! empty($object['proxies']))
        <div class="mt-1 flex flex-wrap gap-1 justify-center">
            @foreach ($object['proxies'] as $proxy)
                <span class="text-[9px] px-1.5 py-0.5 rounded bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-mono">
                    {{ $proxy['nom'] }}
                </span>
            @endforeach
        </div>
    @endif
</div>
