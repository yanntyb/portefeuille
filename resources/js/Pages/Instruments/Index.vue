<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface CatalogLine {
    id: number;
    name: string;
    ticker: string | null;
    type: string;
    typeLabel: string;
    lastPrice: number | null;
    held: boolean;
    quantity: number | null;
    marketValue: number | null;
}

defineProps<{ catalog: { lines: CatalogLine[] } }>();

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Instruments" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header>
                <h1 class="text-2xl font-semibold">Instruments</h1>
                <p class="text-sm text-muted-foreground">Catalogue de tous les instruments</p>
            </header>

            <Card class="border-0 bg-transparent shadow-none rounded-none">
                <CardHeader>
                    <CardTitle>Tous les instruments</CardTitle>
                    <CardDescription>Cliquez une ligne pour ouvrir la fiche</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table v-if="catalog.lines.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nom</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">Dernier prix</TableHead>
                                <TableHead class="text-right">Détenu</TableHead>
                                <TableHead class="text-right">Valeur</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="line in catalog.lines"
                                :key="line.id"
                                class="cursor-pointer hover:bg-muted/50"
                            >
                                <TableCell class="font-medium">
                                    <Link :href="`/instruments/${line.id}`" class="block">
                                        {{ line.name }}
                                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                    </Link>
                                </TableCell>
                                <TableCell>{{ line.typeLabel }}</TableCell>
                                <TableCell class="text-right">
                                    <span v-if="line.lastPrice === null" class="text-muted-foreground">N/D</span>
                                    <span v-else>{{ eur(line.lastPrice) }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span v-if="line.held" class="text-emerald-600 dark:text-emerald-400">Oui</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-right">{{ eur(line.marketValue) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="py-8 text-center text-sm text-muted-foreground">
                        Aucun instrument connu.
                    </p>
                </CardContent>
            </Card>
        </div>
    </main>
</template>
