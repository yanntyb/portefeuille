<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';

/**
 * Le bloc d'attente d'une prop différée : le squelette pendant le chargement, le message hors-ligne
 * quand la prop est rescapée sans valeur, et la sentinelle vide qu'Inertia exige en contenu — le
 * vrai contenu est rendu par l'appelant dans sa branche `v-if`, une fois la prop arrivée.
 *
 * Dix-neuf sections recopiaient ces quinze lignes. Un appelant qui a son propre squelette (un
 * graphe) le passe par le slot `fallback`.
 */
withDefaults(
    defineProps<{
        /** La ou les props Inertia attendues, telles que `<Deferred :data>` les prend. */
        data: string | string[];
        /** Lignes du squelette par défaut. */
        lines?: number;
        /** Hauteur d'une ligne : `h-8` pour une liste, `h-6` pour une grille serrée, `h-16` pour des cartes. */
        lineClass?: string;
    }>(),
    { lines: 3, lineClass: 'h-8' },
);
</script>

<template>
    <Deferred :data="data">
        <template #fallback>
            <slot name="fallback">
                <div class="flex flex-col gap-2">
                    <div
                        v-for="n in lines"
                        :key="n"
                        class="w-full animate-pulse rounded-md bg-muted"
                        :class="lineClass"
                    ></div>
                </div>
            </slot>
        </template>

        <template #rescue>
            <p data-deferred-rescue class="py-8 text-center text-sm text-muted-foreground">
                Données indisponibles hors-ligne.
            </p>
        </template>

        <slot><span /></slot>
    </Deferred>
</template>
