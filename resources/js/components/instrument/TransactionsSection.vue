<script setup lang="ts">
import { ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur } from '@/lib/format';
import type { TransactionLine } from '@/lib/instrument';

defineProps<{ transactions: TransactionLine[] }>();

const isExpanded = ref<boolean>(false);
</script>

<template>
    <section data-section="transactions" class="flex flex-col gap-6 px-6">
        <button
            type="button"
            data-transactions-toggle
            class="flex items-center gap-1.5 self-start text-[17px] leading-none font-bold"
            :aria-expanded="isExpanded"
            @click="isExpanded = !isExpanded"
        >
            <ChevronRight
                class="size-4 text-muted-foreground transition-transform"
                :class="isExpanded ? 'rotate-90' : ''"
            />
            Transactions ({{ transactions.length }})
        </button>

        <div v-if="isExpanded" class="min-w-0">
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
                    <TableRow v-for="(line, index) in transactions" :key="index" data-transaction-row>
                        <TableCell>{{ line.date }}</TableCell>
                        <TableCell :class="line.isSell ? 'text-loss' : 'text-gain'">
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
        </div>
    </section>
</template>
