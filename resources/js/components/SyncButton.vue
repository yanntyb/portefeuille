<script setup lang="ts">
import { useHttp, usePoll } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import type { ComputedRef } from 'vue';
import { isSyncing, syncTitle, type SyncState } from '@/lib/sync';

const props = defineProps<{ state: SyncState }>();

/**
 * `useHttp` et non `router.post` : une visite Inertia rechargerait la page et ferait repartir les
 * quatre groupes différés du tableau de bord, alors que ce clic ne change qu'un statut.
 */
const http = useHttp();

/**
 * Sondage arrêté par défaut : hors synchronisation, il n'y a rien à surveiller. `only` limite la
 * réponse à la prop `sync` — les sections différées ne sont pas redemandées toutes les deux
 * secondes.
 */
const poll = usePoll(2000, { only: ['sync'] }, { autoStart: false });

const busy: ComputedRef<boolean> = computed((): boolean => isSyncing(props.state) || http.processing);

/**
 * Le sondage suit l'état servi, pas le clic : une synchronisation lancée depuis un autre onglet (ou
 * déjà en cours à l'arrivée sur la page) est suivie de la même façon.
 */
watch(
    (): boolean => isSyncing(props.state),
    (syncing: boolean): void => {
        if (syncing) {
            poll.start();

            return;
        }

        poll.stop();
    },
    { immediate: true },
);

const start = (): void => {
    if (busy.value) {
        return;
    }

    /** Le sondage démarre sans attendre la réponse : c'est lui qui verra passer `queued`. */
    poll.start();

    http.post('/synchronisation', {
        /** Hors-ligne, le worker laisse passer le POST et il échoue : le bouton redevient cliquable. */
        onError: (): void => poll.stop(),
    });
};
</script>

<template>
    <button
        type="button"
        data-sync-button
        :data-sync-status="props.state.status"
        :disabled="busy"
        :aria-label="syncTitle(props.state)"
        :title="syncTitle(props.state)"
        class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground disabled:cursor-default disabled:hover:text-muted-foreground"
        @click="start()"
    >
        <RefreshCw class="size-4" :class="{ 'animate-spin': busy }" />
    </button>
</template>
