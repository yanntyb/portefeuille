<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import ConcentrationSection from '@/components/instruments/ConcentrationSection.vue';
import ContributionSection from '@/components/instruments/ContributionSection.vue';
import DrawdownSection from '@/components/instruments/DrawdownSection.vue';
import IncomeSection from '@/components/instruments/IncomeSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import SectorsSection from '@/components/instruments/SectorsSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { Analysis, Drawdown } from '@/lib/analysis';
import type { AnnualIncome, IncomeSummary } from '@/lib/income';
import type { Performance } from '@/lib/performance';
import type { SectorSlice } from '@/lib/sector';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string; hasSectors: boolean; hasIncome: boolean };
    analysis?: Analysis;
    drawdown?: Drawdown;
    performances?: Performance[];
    /** Absentes des expositions sans secteur ni revenu : le serveur ne les envoie pas. */
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classAnalysis(props.assetClass.key);

const analysis = aheadOfNetwork(() => props.analysis, () => cached()?.analysis);
const drawdown = aheadOfNetwork(() => props.drawdown, () => cached()?.drawdown);
const performances = aheadOfNetwork(() => props.performances, () => cached()?.performances);
const sectorBreakdown = aheadOfNetwork(() => props.sectorBreakdown, () => cached()?.sectorBreakdown);
const income = aheadOfNetwork(() => props.income, () => cached()?.income);
const annualIncome = aheadOfNetwork(() => props.annualIncome, () => cached()?.annualIncome);
</script>

<template>
    <Head :title="`Analyse — ${props.assetClass.label}`" />

    <AppPage>
        <ConcentrationSection :concentration="analysis?.concentration ?? null" />
        <ContributionSection :contributions="analysis?.contributions ?? null" />
        <DrawdownSection :drawdown="drawdown" />

        <PerformancesSection :performances="performances" />

        <!--
            Les sections se décident sur la classe, jamais sur la valeur : `aheadOfNetwork` rend
            `null` en attendant, et un `null` ne distingue pas « pas encore » de « jamais ».
        -->
        <IncomeSection
            v-if="props.assetClass.hasIncome"
            :income="income"
            :annual-income="annualIncome"
        />

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
