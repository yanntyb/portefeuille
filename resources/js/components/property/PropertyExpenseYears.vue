<script setup lang="ts">
import { eur } from '@/lib/format';
import type { ExpenseYear } from '@/lib/realEstate';

const props = defineProps<{ years: ExpenseYear[] }>();
</script>

<template>
    <section data-section="expenses" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Charges</h2>

        <p v-if="!props.years.length" class="text-sm text-muted-foreground">Aucune charge enregistrée.</p>

        <div v-for="year in props.years" :key="year.year" class="flex flex-col gap-1">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold">{{ year.year }}</span>
                <span class="font-semibold tabular-nums">{{ eur(year.total) }}</span>
            </div>
            <ul class="flex flex-col">
                <li
                    v-for="entry in year.byCategory"
                    :key="entry.category"
                    class="flex items-center justify-between py-1 text-sm text-muted-foreground"
                >
                    <span>{{ entry.label }}</span>
                    <span class="tabular-nums">{{ eur(entry.amount) }}</span>
                </li>
            </ul>
        </div>
    </section>
</template>
