<div class="min-h-screen flex items-center justify-center bg-gray-950 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
            <p class="mt-2 text-gray-400">Créer votre compte</p>
        </div>

        @if ($tokenInvalid)
            <div class="rounded-lg bg-red-900/30 border border-red-700 p-4 text-center">
                <p class="text-red-400 font-medium">
                    @if ($invitation->isUsed())
                        Ce lien d'invitation a déjà été utilisé.
                    @else
                        Ce lien d'invitation a expiré.
                    @endif
                </p>
                <p class="mt-2 text-gray-400 text-sm">Veuillez contacter un administrateur pour obtenir un nouveau lien.</p>
            </div>
        @else
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-8 shadow-xl">
                <form wire:submit="submit" class="space-y-5">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Nom</label>
                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            class="w-full rounded-lg bg-gray-800 border border-gray-700 text-white px-3 py-2 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                            placeholder="Votre nom"
                            autocomplete="name"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Adresse e-mail</label>
                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            class="w-full rounded-lg bg-gray-800 border border-gray-700 text-white px-3 py-2 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                            placeholder="vous@exemple.com"
                            autocomplete="email"
                        />
                        @error('email')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Mot de passe</label>
                        <input
                            id="password"
                            type="password"
                            wire:model="password"
                            class="w-full rounded-lg bg-gray-800 border border-gray-700 text-white px-3 py-2 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                            placeholder="Minimum 8 caractères"
                            autocomplete="new-password"
                        />
                        @error('password')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-1">Confirmer le mot de passe</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            wire:model="password_confirmation"
                            class="w-full rounded-lg bg-gray-800 border border-gray-700 text-white px-3 py-2 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                            placeholder="Répétez votre mot de passe"
                            autocomplete="new-password"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-amber-500 hover:bg-amber-400 text-black font-semibold px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900"
                    >
                        S'inscrire
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
