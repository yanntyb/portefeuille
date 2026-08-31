<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ChevronDown } from "lucide-vue-next"
import { cn } from "@/lib/utils"

export type SelectOption = {
  value: string
  label: string
}

/**
 * Un `<select>` natif, et non le Select de shadcn-vue / reka-ui. Trois raisons, dans cet ordre.
 *
 * Mobile : le Select de reka-ui est une liste flottante dans un `Popper`. À l'intérieur d'un
 * `DialogContent` en `position: fixed`, avec le clavier virtuel qui redimensionne le viewport, c'est
 * la configuration la plus fragile qui existe — deux verrous de défilement, deux pièges de focus,
 * une liste qui se replace à chaque redimensionnement. Le natif délègue au sélecteur du système.
 *
 * Poids : neuf fichiers et trois primitives de reka-ui pour deux listes plates et courtes, dans un
 * bundle mis en cache immuable par le service worker.
 *
 * Accessibilité : la saisie au clavier, l'annonce du rôle et la gestion du focus sont natives.
 *
 * Ce qu'on perd : la recherche et le style des options. Acceptable pour une poignée d'enveloppes et
 * quelques dizaines d'instruments. Au-delà d'une cinquantaine, passer à `<input list>` +
 * `<datalist>` — natif aussi — avant d'envisager reka-ui.
 *
 * Le dossier ne s'appelle pas `select/` pour qu'un futur `shadcn-vue add select` ne l'écrase pas.
 */
const props = defineProps<{
  options: SelectOption[]
  /** Première entrée inerte, quand aucune valeur n'est encore choisie. */
  placeholder?: string
  id?: string
  invalid?: boolean
  disabled?: boolean
  class?: HTMLAttributes["class"]
}>()

const model = defineModel<string>({ default: "" })
</script>

<template>
  <div class="relative">
    <select
      :id="props.id"
      v-model="model"
      data-slot="native-select"
      :disabled="props.disabled"
      :aria-invalid="props.invalid || undefined"
      :class="cn(
        'border-input dark:bg-input/30 flex h-9 w-full appearance-none rounded-md border bg-transparent py-1 pr-8 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
        model === '' ? 'text-muted-foreground' : 'text-foreground',
        props.class,
      )"
    >
      <option v-if="props.placeholder !== undefined" value="" disabled>
        {{ props.placeholder }}
      </option>
      <option
        v-for="option in props.options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>

    <!-- Le chevron est décoratif : le sélecteur natif n'en dessine pas sur tous les systèmes. -->
    <ChevronDown
      class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
      aria-hidden="true"
    />
  </div>
</template>
