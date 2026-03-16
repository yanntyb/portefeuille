<x-filament::page>
    <div class="flex flex-col gap-6 max-w-sm">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-950 dark:text-white">Rôle simulé</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    @if ($this->simulateUser)
                        <span class="inline-flex items-center gap-1 text-warning-600 dark:text-warning-400 font-medium">
                            <x-heroicon-s-user class="size-4" />
                            Utilisateur
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-success-600 dark:text-success-400 font-medium">
                            <x-heroicon-s-shield-check class="size-4" />
                            Admin
                        </span>
                    @endif
                </p>
            </div>
            {{ $this->toggleRoleAction }}
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">
            Cet état n'est pas persistant en base de données. Il est stocké en session et réinitialisé à la déconnexion.
        </p>
    </div>

    <x-filament-actions::modals />
</x-filament::page>
