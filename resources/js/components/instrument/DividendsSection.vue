<script setup lang="ts">
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur, frDate, pct } from '@/lib/format';
import type { AssetDividendHistory } from '@/lib/income';

const props = defineProps<{ dividends: AssetDividendHistory }>();

const heading = computed<string>(() => `Dividendes (${props.dividends.receipts.length})`);

/** Un montant par action se lit au millième : 0,51 € et 0,515 € ne sont pas le même dividende. */
const perShare = (value: number): string => eur(value, 3);
</script>

<template>
    <section data-section="dividends" class="flex flex-col gap-6 px-6">
        <div class="flex flex-col gap-1.5">
            <h2 class="text-[17px] leading-none font-bold">{{ heading }}</h2>
            <p class="text-sm text-muted-foreground">
                <span data-dividend-total>{{ eur(props.dividends.totalReceived) }}</span> perçus,
                dont <span data-dividend-last12>{{ eur(props.dividends.last12Months) }}</span> sur douze mois
                <template v-if="props.dividends.yieldOnCost !== null">
                    · <span data-dividend-yield>{{ pct(props.dividends.yieldOnCost) }}</span> du prix de revient
                </template>
                <template v-if="props.dividends.estimatedAnnual > 0">
                    · <span data-dividend-estimate>~{{ eur(props.dividends.estimatedAnnual) }}</span> estimés sur douze mois
                </template>
            </p>
        </div>

        <div class="min-w-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Détachement</TableHead>
                        <TableHead class="text-right">Par action</TableHead>
                        <TableHead class="text-right">Quantité</TableHead>
                        <TableHead class="text-right">Perçu</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="receipt in props.dividends.receipts"
                        :key="receipt.exDate"
                        data-dividend-row
                    >
                        <TableCell>{{ frDate(receipt.exDate) }}</TableCell>
                        <TableCell class="text-right" data-dividend-per-share>{{ perShare(receipt.amountPerShare) }}</TableCell>
                        <TableCell class="text-right" data-dividend-quantity>{{ receipt.quantity }}</TableCell>
                        <TableCell class="text-right font-semibold" data-dividend-amount>{{ eur(receipt.amount) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </section>
</template>
