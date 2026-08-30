<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import ConcentrationSection from '@/components/instruments/ConcentrationSection.vue';
import ContributionSection from '@/components/instruments/ContributionSection.vue';
import DrawdownSection from '@/components/instruments/DrawdownSection.vue';
import type { Analysis, Drawdown } from '@/lib/analysis';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string };
    analysis?: Analysis;
    drawdown?: Drawdown;
}>();
</script>

<template>
    <Head :title="`Analyse — ${props.assetClass.label}`" />

    <AppPage>
        <ConcentrationSection :concentration="props.analysis?.concentration ?? null" />
        <ContributionSection :contributions="props.analysis?.contributions ?? null" />
        <DrawdownSection :drawdown="props.drawdown ?? null" />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.assetClass.label, href: `/${props.assetClass.slug}` },
            { label: 'Analyse' },
        ]"
    />
</template>
