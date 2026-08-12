<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur, frDate, gainClass, pct } from '@/lib/format';
import type { Performance } from '@/lib/performance';

const props = withDefaults(
    defineProps<{ performances: Performance[]; currencyDigits?: number }>(),
    { currencyDigits: 2 },
);

const amount = (value: number): string => eur(value, props.currencyDigits);

const signedAmount = (value: number): string => (value > 0 ? `+${amount(value)}` : amount(value));
</script>

<template>
    <Table data-testid="performance-table">
        <TableHeader>
            <TableRow>
                <TableHead>Période</TableHead>
                <TableHead class="text-right">Depuis</TableHead>
                <TableHead class="text-right">Valeur début</TableHead>
                <TableHead class="text-right">Apports</TableHead>
                <TableHead class="text-right">Gain</TableHead>
                <TableHead class="text-right">Perf.</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="perf in props.performances" :key="perf.key">
                <TableCell class="font-medium">{{ perf.label }}</TableCell>
                <TableCell class="text-right text-muted-foreground">{{ frDate(perf.startDate) }}</TableCell>
                <TableCell class="text-right">{{ amount(perf.valueStart) }}</TableCell>
                <TableCell class="text-right">{{ signedAmount(perf.contributions) }}</TableCell>
                <TableCell class="text-right" :class="gainClass(perf.gain)">{{ signedAmount(perf.gain) }}</TableCell>
                <TableCell class="text-right" :class="gainClass(perf.pct)">{{ pct(perf.pct) }}</TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
