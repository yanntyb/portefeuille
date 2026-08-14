<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { eur } from '@/lib/format';
import type { TransactionLine } from '@/lib/instrument';

const COLLAPSED_TRANSACTION_COUNT = 10;

const props = defineProps<{ transactions: TransactionLine[] }>();

const isExpanded = ref<boolean>(false);

const hiddenCount = computed<number>(() =>
    Math.max(0, props.transactions.length - COLLAPSED_TRANSACTION_COUNT),
);

const visibleTransactions = computed<TransactionLine[]>(() =>
    isExpanded.value ? props.transactions : props.transactions.slice(0, COLLAPSED_TRANSACTION_COUNT),
);
</script>

<template>
    <section data-section="transactions" class="flex flex-col gap-6 px-6">
        <div class="flex flex-col gap-1.5">
            <h2 class="leading-none font-semibold">Transactions</h2>
            <p class="text-sm text-muted-foreground">Mes mouvements sur cet actif</p>
        </div>

        <div class="min-w-0">
            <Table v-if="transactions.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Sens</TableHead>
                        <TableHead class="text-right">Quantité</TableHead>
                        <TableHead class="text-right">Prix unit.</TableHead>
                        <TableHead class="text-right">Frais</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(line, index) in visibleTransactions" :key="index" data-transaction-row>
                        <TableCell>{{ line.date }}</TableCell>
                        <TableCell :class="line.isSell ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ line.typeLabel }}
                        </TableCell>
                        <TableCell class="text-right">{{ line.quantity }}</TableCell>
                        <TableCell class="text-right">{{ eur(line.unitPrice) }}</TableCell>
                        <TableCell class="text-right">{{ eur(line.fees) }}</TableCell>
                        <TableCell class="text-right">{{ eur(line.total) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune transaction sur cet actif.
            </p>

            <Button
                v-if="hiddenCount > 0"
                data-transactions-toggle
                variant="ghost"
                size="sm"
                class="mt-3 w-full text-muted-foreground"
                @click="isExpanded = !isExpanded"
            >
                {{ isExpanded ? 'Réduire' : `Voir les ${hiddenCount} autres` }}
            </Button>
        </div>
    </section>
</template>
