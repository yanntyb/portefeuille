<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur as formatEur, gainClass, pct } from '@/lib/format';
import type { HoldingLine } from '@/lib/portfolio';

defineProps<{ holdings: HoldingLine[]; hiddenAssetIds: Set<number> }>();

defineEmits<{ toggle: [assetId: number] }>();

const eur = (value: number | null): string => formatEur(value, 0);
</script>

<template>
    <section data-section="holdings" class="px-6">
        <Card>
            <CardHeader>
                <CardTitle>Positions</CardTitle>
            </CardHeader>
            <CardContent class="min-w-0 px-2 sm:px-6">
                <Table v-if="holdings.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10"></TableHead>
                            <TableHead>Actif</TableHead>
                            <TableHead class="text-right">Valeur</TableHead>
                            <TableHead class="text-right">+/-</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead class="text-right">Quantité</TableHead>
                            <TableHead class="text-right">Dernier prix</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(line, index) in holdings" :key="index">
                            <TableCell class="w-10">
                                <button
                                    type="button"
                                    class="text-muted-foreground transition-colors hover:text-foreground"
                                    :aria-label="hiddenAssetIds.has(line.assetId) ? 'Afficher' : 'Masquer'"
                                    @click="$emit('toggle', line.assetId)"
                                >
                                    <EyeOff v-if="hiddenAssetIds.has(line.assetId)" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </button>
                            </TableCell>
                            <TableCell class="font-medium" :class="hiddenAssetIds.has(line.assetId) ? 'opacity-40' : ''">
                                <Link :href="`/instruments/${line.assetId}`" class="hover:underline">
                                    {{ line.assetName }}
                                    <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                </Link>
                            </TableCell>
                            <TableCell class="text-right">{{ eur(line.marketValue) }}</TableCell>
                            <TableCell class="text-right" :class="gainClass(line.gain)">{{ pct(line.gainPct) }}</TableCell>
                            <TableCell>{{ line.typeLabel }}</TableCell>
                            <TableCell class="text-right">{{ line.quantity }}</TableCell>
                            <TableCell class="text-right">
                                <span v-if="line.lastPrice === null" class="text-muted-foreground" title="Prix indisponible">N/D</span>
                                <span v-else>{{ eur(line.lastPrice) }}</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Aucune position pour le moment.
                </p>
            </CardContent>
        </Card>
    </section>
</template>
