<script setup lang="ts">
import { computed, ref } from 'vue';
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
import { isWideViewport } from '@/lib/viewport';
import type { TransactionLine } from '@/lib/instrument';

const props = defineProps<{ transactions: TransactionLine[] }>();

const isExpanded = ref<boolean>(false);

/**
 * Sur mobile la section occupe déjà sa propre page du carrousel : le pli n'aurait plus rien à
 * cacher, il ajouterait un geste avant de lire le tableau. Le repli ne sert que le grand écran,
 * où les sections se suivent dans une même colonne.
 */
const isFoldable = isWideViewport;

const showsTransactions = computed<boolean>(() => !isFoldable.value || isExpanded.value);

const heading = computed<string>(() => `Transactions (${props.transactions.length})`);
</script>

<template>
    <section data-section="transactions" class="flex flex-col gap-6 px-6">
        <button
            v-if="isFoldable"
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
            {{ heading }}
        </button>

        <h2 v-else class="text-[17px] leading-none font-bold">{{ heading }}</h2>

        <div v-if="showsTransactions" class="min-w-0">
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
