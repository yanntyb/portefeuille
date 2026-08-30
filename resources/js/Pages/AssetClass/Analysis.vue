<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import SectorsSection from '@/components/instruments/SectorsSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { Performance } from '@/lib/performance';
import type { SectorSlice } from '@/lib/sector';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string; hasSectors: boolean };
    performances?: Performance[];
    /** Absente des expositions sans secteur : le serveur ne l'envoie pas. */
    sectorBreakdown?: SectorSlice[];
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classAnalysis(props.assetClass.key);

const performances = aheadOfNetwork(() => props.performances, () => cached()?.performances);
const sectorBreakdown = aheadOfNetwork(() => props.sectorBreakdown, () => cached()?.sectorBreakdown);
</script>

<template>
    <Head :title="`Analyse — ${props.assetClass.label}`" />

    <AppPage>
        <PerformancesSection :performances="performances" />

        <!--
            La section se décide sur la classe, jamais sur la valeur : `aheadOfNetwork` rend
            `null` en attendant, et un `null` ne distingue pas « pas encore » de « jamais ».
        -->
        <SectorsSection v-if="props.assetClass.hasSectors" :slices="sectorBreakdown" />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.assetClass.label, href: `/${props.assetClass.slug}` },
            { label: 'Analyse' },
        ]"
    />
</template>
