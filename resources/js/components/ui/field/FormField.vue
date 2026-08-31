<script setup lang="ts">
import { computed } from "vue"
import { Label } from "@/components/ui/label"

/**
 * Un champ : son intitulé, son contrôle, son erreur. Sept champs sans ce cadre, ce serait soixante
 * lignes de gabarit recopié.
 *
 * Ce n'est pas le `Form` de shadcn : pas de contexte, pas de validation côté client, aucun
 * `FormItem`/`FormControl`/`FormMessage`. Les erreurs viennent du serveur, telles quelles.
 */
const props = defineProps<{
  /** Doit correspondre à l'`id` du contrôle passé en slot, pour que l'intitulé le désigne. */
  id: string
  label: string
  /** Message du serveur ; Inertia rend une chaîne par champ. */
  error?: string | null
  /** Précision permanente, sous le champ : une unité, un format attendu. */
  hint?: string
}>()

const describedBy = computed<string | undefined>(() => {
  if (props.error) {
    return `${props.id}-error`
  }

  return props.hint === undefined ? undefined : `${props.id}-hint`
})
</script>

<template>
  <div class="flex min-w-0 flex-col gap-1.5">
    <Label :for="props.id">{{ props.label }}</Label>

    <!--
      Le contrôle reçoit son `aria-describedby` et son `aria-invalid` par le slot : c'est lui qui
      porte l'`id`, le cadre ne peut pas les poser à sa place.
    -->
    <slot :described-by="describedBy" :invalid="Boolean(props.error)" />

    <p
      v-if="props.error"
      :id="`${props.id}-error`"
      data-field-error
      class="text-xs text-destructive"
    >
      {{ props.error }}
    </p>

    <p
      v-else-if="props.hint !== undefined"
      :id="`${props.id}-hint`"
      class="text-xs text-muted-foreground"
    >
      {{ props.hint }}
    </p>
  </div>
</template>
