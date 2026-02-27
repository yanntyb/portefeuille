<x-filament::page>
    {{ $this->form }}

    @if ($this->hasResults)
        <div class="mt-6">
            {{ $this->table }}

            <div class="mt-4 flex items-center px-6 py-4 text-sm text-gray-600 dark:text-gray-400" style="gap: 2rem;">
                <div>
                    <span class="font-medium text-gray-950 dark:text-white">Total investi :</span>
                    {{ number_format($this->totalInvested, 2, ',', ' ') }} €
                </div>
                <div>
                    <span class="font-medium text-gray-950 dark:text-white">Reste non investi :</span>
                    <span class="font-semibold {{ $this->remainder >= 0 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ number_format($this->remainder, 2, ',', ' ') }} €
                    </span>
                </div>
            </div>
        </div>
    @endif
</x-filament::page>
