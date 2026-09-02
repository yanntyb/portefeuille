<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import CatalogList from '@/components/instruments/CatalogList.vue';
import InstrumentSearchPanel, { type CreatedInstrument } from '@/components/instruments/InstrumentSearchPanel.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { filterCatalog, type CatalogLine } from '@/lib/catalog';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string };
    catalog: CatalogLine[];
}>();

/** La recherche ne quitte pas la page : le catalogue d'une poche tient en mémoire. */
const term = ref<string>('');

const lines = computed<CatalogLine[]>(() => filterCatalog(props.catalog, term.value));

/**
 * L'ajout s'accroche à l'état vide du filtre plutôt qu'à un second champ : c'est là que le manque
 * se constate, et l'écran ne montre jamais deux recherches à la fois.
 */
const adding: Ref<boolean> = ref(false);

/**
 * Le terme dont l'ouverture automatique a déjà été refusée. Sans lui, fermer le panneau le
 * rouvrirait aussitôt : le filtre ne rend toujours rien, c'est la condition même d'ouverture.
 */
const dismissed: Ref<string | null> = ref(null);

/**
 * Débouncé, et le délai n'est pas cosmétique : le filtre local est synchrone, donc la première
 * lettre d'un nom absent du catalogue rendrait déjà zéro ligne et ouvrirait le panneau au milieu
 * de la frappe. On attend que la frappe se pose avant d'aller chez Yahoo.
 */
const AUTO_OPEN_DELAY_MS = 400;

let timer: ReturnType<typeof setTimeout> | null = null;

watch([term, lines], (): void => {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    if (adding.value || term.value.trim() === '' || lines.value.length > 0) {
        return;
    }

    timer = setTimeout((): void => {
        if (term.value.trim() !== dismissed.value) {
            adding.value = true;
        }
    }, AUTO_OPEN_DELAY_MS);
});

/** Fermeture par le bouton, par Échap ou par le fond : toutes retiennent le terme refusé. */
watch(adding, (open: boolean): void => {
    if (!open) {
        dismissed.value = term.value.trim();
    }
});

onBeforeUnmount((): void => {
    if (timer !== null) {
        clearTimeout(timer);
    }
});

const onCreated = (instrument: CreatedInstrument): void => {
    adding.value = false;

    /**
     * La page ne vaut que pour une exposition. Un instrument rangé ailleurs n'apparaîtrait pas au
     * rechargement, et la création aurait l'air ratée : on va alors à son catalogue.
     */
    if (instrument.assetClass !== props.assetClass.key) {
        router.visit(`/${instrument.assetClassSlug}/catalogue`);

        return;
    }

    term.value = '';
    dismissed.value = null;
    router.reload({ only: ['catalog'] });
};
</script>

<template>
    <Head :title="`${props.assetClass.label} — catalogue`" />

    <AppPage>
        <section class="flex flex-col gap-6 px-6">
            <h1 class="text-[17px] leading-none font-bold">Catalogue</h1>

            <Input
                v-model="term"
                data-catalog-search
                type="search"
                aria-label="Rechercher un instrument"
                placeholder="Rechercher un instrument"
            />

            <!-- La section porte `px-6`, les lignes leur propre `px-3` : le retrait les ramène à la marge des listes. -->
            <div class="-mx-3">
                <CatalogList
                    :lines="lines"
                    :empty-label="term.trim() === '' ? 'Aucun instrument dans cette classe.' : 'Aucun instrument ne correspond à cette recherche.'"
                />
            </div>

            <button
                v-if="term.trim() !== '' && lines.length === 0"
                type="button"
                data-catalog-yahoo
                class="self-start text-sm font-semibold text-primary"
                @click="adding = true"
            >
                Chercher « {{ term.trim() }} » chez Yahoo
            </button>
        </section>

        <Dialog v-model:open="adding">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter un instrument</DialogTitle>
                    <DialogDescription>La recherche par nom ou par ticker, en base ou chez Yahoo.</DialogDescription>
                </DialogHeader>

                <InstrumentSearchPanel
                    :initial-term="term.trim()"
                    :exposure="props.assetClass.key"
                    @created="onCreated"
                    @cancel="adding = false"
                    @open="(payload) => router.visit(`/asset/${payload.id}`)"
                />
            </DialogContent>
        </Dialog>
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.assetClass.label, href: `/${props.assetClass.slug}` },
            { label: 'Catalogue' },
        ]"
    />
</template>
