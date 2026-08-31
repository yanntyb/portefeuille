<script setup lang="ts">
import type { AcceptableValue } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { Check, ChevronDown } from "lucide-vue-next"
import {
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxRoot,
  ComboboxTrigger,
  ComboboxViewport,
} from "reka-ui"
import { nextTick, ref, useTemplateRef, watch } from "vue"
import type { Ref } from "vue"
import type { SelectOption } from "@/components/ui/native-select"
import { cn } from "@/lib/utils"

/**
 * Un champ qui se cherche : on tape, la liste se réduite, on retient une entrée.
 *
 * `NativeSelect` écarte reka-ui pour trois raisons, dont la première est décisive : une liste
 * flottante dans un `DialogContent` en `position: fixed`, avec le clavier virtuel qui redimensionne
 * le viewport, c'est deux verrous de défilement, deux pièges de focus et une liste qui se replace
 * sans cesse. Ce composant ne contredit pas cette note, il en sort par le haut : `ComboboxContent`
 * est en `position="inline"` — son défaut — et n'instancie alors **pas** `PopperContent`. La liste
 * est un frère du champ, dans le flux, placée en CSS, à l'intérieur du conteneur qui défile déjà.
 * Rien ne flotte, `bodyLock` reste faux, aucun second verrou n'apparaît. Aucun `ComboboxPortal`
 * non plus : le portail est précisément le mode d'échec que la note redoutait.
 *
 * Ce qu'on paie : `ComboboxContentImpl` importe `PopperContent` statiquement, donc floating-ui
 * entre dans le bundle sans jamais s'exécuter. Une douzaine de kilo-octets, précachés une fois pour
 * toutes par le service worker. Ce qu'on achète : un filtrage insensible aux accents et à la casse
 * — « societe » trouve « Société » — qu'aucun `<select>` ni `<datalist>` ne donne, sur la seule
 * liste du formulaire qui grossira sans limite.
 *
 * Le dossier ne s'appelle ni `combobox/` ni `command/` : ce sont les deux noms qu'un futur
 * `shadcn-vue add combobox` réclamerait, et il les écraserait. Même précaution que `native-select/`,
 * et la paire se lit d'elle-même — le select qu'on cherche, le select natif.
 */
const props = defineProps<{
  options: SelectOption[]
  /** L'attribut natif du champ vide, et non une entrée inerte comme dans un `<select>`. */
  placeholder?: string
  /** Ce qu'on lit quand la frappe ne rencontre rien. Aucune création : on n'invente pas une entrée. */
  empty?: string
  id?: string
  invalid?: boolean
  disabled?: boolean
  /**
   * Déclaré en prop et non laissé en attribut de passe : la racine n'est pas l'`<input>`, l'attribut
   * y tomberait et le message d'erreur du serveur ne serait annoncé par personne.
   */
  ariaDescribedby?: string
  class?: HTMLAttributes["class"]
}>()

const model = defineModel<string>({ default: "" })

/**
 * `change` en plus d'`update:modelValue`, pour tenir la promesse d'un `<select>` natif : l'appelant
 * veut réagir à un vrai choix, pas à toute écriture du modèle.
 */
const emit = defineEmits<{ change: [value: string] }>()

const open: Ref<boolean> = ref(false)
const query: Ref<string> = ref("")
const input = useTemplateRef("input")

/**
 * Sert deux fois : au `display-value` de reka, qui repose le texte du champ quand la valeur change,
 * et au guet ci-dessous, qui le repose quand ce sont les options qui changent. Un intitulé
 * introuvable rend la chaîne vide — jamais l'identifiant brut, qui ne dit rien au lecteur.
 */
const labelOf = (value: AcceptableValue): string =>
  props.options.find((option: SelectOption): boolean => option.value === value)?.label ?? ""

/**
 * `:model-value` et `@update:model-value` plutôt que `v-model` sur la racine : c'est le seul point
 * où l'on écrit le modèle, donc le seul où décider si le choix mérite un `change`. Le modèle est
 * écrit **avant** l'émission, pour que l'appelant lise déjà la nouvelle valeur — l'ordre exact d'un
 * `change` natif, qui remonte du `<select>` une fois la valeur posée.
 */
const onChosen = (value: AcceptableValue): void => {
  const chosen = typeof value === "string" ? value : ""

  if (chosen === "" || chosen === model.value) {
    return
  }

  model.value = chosen
  emit("change", chosen)

  /**
   * Le focus revient au champ et l'intitulé s'y réinstalle : le prochain mot doit le remplacer.
   * Deux cycles, parce que reka repose le texte à son propre `nextTick` — sélectionner au premier
   * ne sélectionnerait qu'un champ encore vide.
   */
  void nextTick((): void => {
    void nextTick(selectAll)
  })
}

/**
 * Reka resynchronise le texte affiché quand la **valeur** change ; il ne sait rien d'un catalogue
 * qui arrive après. Or les options viennent d'un `fetch` postérieur au montage : sans ce guet, une
 * correction ouverte sur un actif déjà choisi montrerait un champ vide.
 *
 * Seulement liste fermée : pendant la recherche, le champ appartient à qui tape.
 */
watch(
  [(): SelectOption[] => props.options, model, open],
  (): void => {
    if (!open.value) {
      query.value = labelOf(model.value)
    }
  },
  { immediate: true },
)

/**
 * Le champ montre l'intitulé retenu, et reka prend sa valeur entière pour terme de recherche. Sans
 * cette sélection, la frappe suivante se collerait à l'intitulé — « tot » après « AIR LIQUIDE ·
 * AI.PA » cherchait « totAIR LIQUIDE · AI.PA », donc ne trouvait plus rien.
 *
 * On sélectionne donc le texte à chaque fois que le champ (re)devient un point de départ : à la
 * prise de focus, et juste après un choix, où le focus revient au champ sans nouvel événement. La
 * première touche remplace la sélection, et le terme de recherche redevient ce qu'on tape.
 */
const selectAll = (): void => {
  const element = input.value?.$el

  if (element instanceof HTMLInputElement) {
    element.select()
  }
}

/**
 * Liste ouverte sans entrée surlignée — l'état vide —, reka laisse passer `Entrée`, qui soumettrait
 * le formulaire. Une recherche infructueuse ne doit rien envoyer.
 */
const onEnter = (event: KeyboardEvent): void => {
  if (open.value) {
    event.preventDefault()
  }
}
</script>

<template>
  <ComboboxRoot
    v-model:open="open"
    :model-value="model"
    :disabled="props.disabled"
    open-on-click
    class="relative"
    @update:model-value="onChosen"
  >
    <!--
      `open-on-focus` reste à faux : traverser le formulaire au clavier ne doit pas déplier une
      liste au passage.
    -->
    <ComboboxInput
      :id="props.id"
      ref="input"
      v-model="query"
      :display-value="labelOf"
      data-slot="search-select"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :aria-invalid="props.invalid || undefined"
      :aria-describedby="props.ariaDescribedby"
      :class="cn(
        'border-input dark:bg-input/30 placeholder:text-muted-foreground flex h-9 w-full min-w-0 rounded-md border bg-transparent py-1 pr-8 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
        props.class,
      )"
      @focus="selectAll"
      @keydown.enter="onEnter"
    />

    <!-- Le chevron du natif, mais cliquable : dérouler tout sans avoir rien à taper. -->
    <ComboboxTrigger
      class="absolute top-1/2 right-2 -translate-y-1/2 disabled:opacity-50"
      aria-label="Dérouler la liste"
    >
      <ChevronDown class="text-muted-foreground size-4" aria-hidden="true" />
    </ComboboxTrigger>

    <ComboboxContent
      class="border-border bg-popover text-popover-foreground absolute top-full right-0 left-0 z-50 mt-1 overflow-hidden rounded-md border shadow-md"
    >
      <ComboboxViewport class="max-h-56 overflow-y-auto p-1">
        <ComboboxEmpty data-search-select-empty class="text-muted-foreground px-2 py-1.5 text-sm">
          {{ props.empty ?? "Aucun résultat" }}
        </ComboboxEmpty>

        <ComboboxItem
          v-for="option in props.options"
          :key="option.value"
          :value="option.value"
          :data-search-select-option="option.value"
          class="data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none"
        >
          <span class="truncate">{{ option.label }}</span>

          <ComboboxItemIndicator class="ml-auto">
            <Check class="size-4" aria-hidden="true" />
          </ComboboxItemIndicator>
        </ComboboxItem>
      </ComboboxViewport>
    </ComboboxContent>
  </ComboboxRoot>
</template>
