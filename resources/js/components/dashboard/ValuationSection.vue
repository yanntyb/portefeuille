<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { eur as formatEur, gainClass, pct } from '@/lib/format';
import type { PortfolioOverview } from '@/lib/portfolio';

defineProps<{ overview: PortfolioOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);
</script>

<template>
    <section data-section="valuation" class="px-6">
        <Card>
            <CardHeader>
                <CardTitle>Valorisation</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-semibold">
                    {{ eur(overview.totalValue) }}
                    <span class="ml-2 text-sm" :class="gainClass(overview.totalGain)">({{ pct(overview.totalGainPct) }})</span>
                </p>
            </CardContent>
        </Card>
    </section>
</template>
