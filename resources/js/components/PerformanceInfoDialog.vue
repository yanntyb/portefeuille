<script setup lang="ts">
import { Info } from "lucide-vue-next"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"

defineProps<{ variant: "global" | "periods" }>()
</script>

<template>
  <Dialog>
    <DialogTrigger as-child>
      <Button
        variant="ghost"
        size="icon-sm"
        class="text-muted-foreground hover:text-foreground"
        :aria-label="variant === 'global' ? 'Comment lire le gain global' : 'Comment lire les performances par période'"
      >
        <Info />
      </Button>
    </DialogTrigger>

    <DialogContent>
      <template v-if="variant === 'global'">
        <DialogHeader>
          <DialogTitle>Comment lire le gain global</DialogTitle>
          <DialogDescription>Le grand chiffre en haut du tableau de bord.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-3 text-sm text-foreground">
          <p>
            C'est le <strong>gain (ou la perte) total</strong> de tes positions actuelles :
            leur valeur au marché aujourd'hui comparée à ce que tu as payé pour les acquérir.
          </p>
          <p>
            Le pourcentage = gain ÷ montant investi (prix de revient : prix moyen d'achat ×
            quantité détenue).
          </p>
          <p class="rounded-md bg-muted px-3 py-2 font-mono text-xs">
            +28 116 € / 54 100 € = +52,0 %
          </p>
          <p class="text-muted-foreground">
            Ce chiffre ne dépend d'aucune période : il prend en compte tout l'argent placé
            depuis le début.
          </p>
        </div>
      </template>

      <template v-else>
        <DialogHeader>
          <DialogTitle>Comment lire les performances par période</DialogTitle>
          <DialogDescription>Le tableau YTD, 1 mois, … 4 ans.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-3 text-sm text-foreground">
          <p>
            Chaque ligne montre la <strong>performance du portefeuille sur une période</strong>
            (depuis le début d'année, le dernier mois, la dernière année, etc.).
          </p>
          <p class="rounded-md bg-muted px-3 py-2 font-mono text-xs">
            (valeur fin - valeur début - versements de la période) / valeur au début
          </p>
          <p>
            On <strong>retire les versements</strong> faits pendant la période (tes achats
            programmés) pour ne mesurer que la vraie performance, pas l'argent ajouté.
          </p>
          <p>
            La colonne <strong>Apports</strong> montre justement les versements de la période
            qui sont retirés du calcul, et <strong>Gain</strong> le résultat en euros une fois
            ces versements exclus. <strong>Valeur début</strong> est le dénominateur, pris au
            jour indiqué dans <strong>Depuis</strong>.
          </p>
          <p>
            C'est pour ça que ça diffère du % global : ici le dénominateur est la valeur
            <strong>au début de la période</strong> (pas le coût total), et les versements
            sont exclus.
          </p>
          <p class="text-muted-foreground">
            Ces chiffres sont <strong>cumulés, pas annualisés</strong> : « 4 ans +640 % »
            veut dire ×7,4 par rapport à il y a 4 ans, pas 640 % par an. Ils paraissent
            énormes sur les longues périodes car au démarrage le portefeuille était petit
            (petite base de départ = grand pourcentage).
          </p>
        </div>
      </template>
    </DialogContent>
  </Dialog>
</template>
