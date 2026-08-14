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
          <DialogDescription>La liste YTD, 1 mois, … Max.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-3 text-sm text-foreground">
          <p>
            Chaque ligne montre la <strong>performance du portefeuille sur une période</strong>
            (depuis le début d'année, le dernier mois, la dernière année, etc.).
            <strong>Max</strong> couvre tout l'historique, depuis le premier achat.
          </p>
          <p class="rounded-md bg-muted px-3 py-2 font-mono text-xs">
            chaque jour : (valeur du jour - valeur de la veille - versements du jour) / valeur de la veille
            <br />
            puis on enchaîne les jours de la période
          </p>
          <p>
            On <strong>retire les versements</strong> faits pendant la période (tes achats
            programmés) pour ne mesurer que la vraie performance, pas l'argent ajouté.
            Comme le calcul se fait jour par jour, <strong>la date de tes versements ne
            change rien</strong> au pourcentage.
          </p>
          <p>
            <strong>Gain</strong> est le résultat en euros une fois ces versements exclus. Au
            survol d'une ligne, <strong>Apports</strong> montre justement les versements de la
            période retirés du calcul, et <strong>Valeur début</strong> la valorisation au
            premier jour de la période.
          </p>
          <p>
            C'est pour ça que ça diffère du % global : celui-ci compare le gain au coût total
            de tes positions, alors qu'ici on suit la variation de valeur d'un jour au
            suivant, versements exclus.
          </p>
          <p class="text-muted-foreground">
            Ces chiffres sont <strong>cumulés, pas annualisés</strong> : « Max +640 % » veut
            dire ×7,4 depuis le premier achat, pas 640 % par an.
          </p>
        </div>
      </template>
    </DialogContent>
  </Dialog>
</template>
