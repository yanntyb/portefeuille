<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import CatalogList from '@/components/instruments/CatalogList.vue';
import { Input } from '@/components/ui/input';
import { filterCatalog, type CatalogLine } from '@/lib/catalog';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string };
    catalog: CatalogLine[];
}>();

/** La recherche ne quitte pas la page : le catalogue d'une poche tient en mémoire. */
const term = ref<string>('');

const lines = computed<CatalogLine[]>(() => filterCatalog(props.catalog, term.value));
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
        </section>
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.assetClass.label, href: `/${props.assetClass.slug}` },
            { label: 'Catalogue' },
        ]"
    />
</template>
