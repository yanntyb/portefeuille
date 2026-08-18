<script setup lang="ts">
import { computed } from 'vue';
import { syncedAtLabel } from '@/lib/format';
import {
    applyUpdate,
    canInstall,
    dismissInstall,
    lastSyncedAt,
    promptInstall,
    stale,
    updateAvailable,
} from '@/lib/serviceWorker';

type BannerState = 'update' | 'stale' | 'install' | 'none';

/**
 * Priorité stricte : recharger pour une nouvelle version règle aussi la fraîcheur, donc les
 * deux premiers états n'ont jamais à cohabiter.
 */
const state = computed<BannerState>(() => {
    if (updateAvailable.value) {
        return 'update';
    }

    if (stale.value) {
        return 'stale';
    }

    return canInstall.value ? 'install' : 'none';
});

const syncedLabel = computed<string>(() => syncedAtLabel(lastSyncedAt.value));

const refresh = (): void => {
    window.location.reload();
};
</script>

<template>
    <!-- Position fixe : le bandeau ne décale pas la mise en page, donc ne casse ni le carrousel ni la hauteur des graphes. -->
    <div
        v-if="state !== 'none'"
        :data-pwa-banner="state"
        class="fixed inset-x-0 bottom-0 z-50 mx-auto flex w-full max-w-[520px] items-center justify-between gap-3 rounded-t-xl bg-foreground px-4 py-3 text-sm text-background shadow-lg"
    >
        <template v-if="state === 'update'">
            <span>Nouvelle version disponible</span>
            <button
                type="button"
                data-pwa-action="reload"
                class="shrink-0 rounded-md bg-background px-3 py-1.5 font-medium text-foreground"
                @click="applyUpdate"
            >
                Recharger
            </button>
        </template>

        <template v-else-if="state === 'stale'">
            <span>{{ syncedLabel }}</span>
            <button
                type="button"
                data-pwa-action="refresh"
                class="shrink-0 rounded-md bg-background px-3 py-1.5 font-medium text-foreground"
                @click="refresh"
            >
                Actualiser
            </button>
        </template>

        <template v-else>
            <span>Installer l'app</span>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    data-pwa-action="dismiss"
                    class="px-2 py-1.5 opacity-70"
                    @click="dismissInstall"
                >
                    Plus tard
                </button>
                <button
                    type="button"
                    data-pwa-action="install"
                    class="rounded-md bg-background px-3 py-1.5 font-medium text-foreground"
                    @click="promptInstall"
                >
                    Installer
                </button>
            </div>
        </template>
    </div>
</template>
