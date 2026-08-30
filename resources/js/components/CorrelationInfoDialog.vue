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
</script>

<template>
  <Dialog>
    <DialogTrigger as-child>
      <Button
        variant="ghost"
        size="icon-sm"
        class="text-muted-foreground hover:text-foreground"
        aria-label="Comment lire les corrélations"
      >
        <Info />
      </Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>Comment lire les corrélations</DialogTitle>
        <DialogDescription>Le tableau croisé de tes plus grosses lignes.</DialogDescription>
      </DialogHeader>

      <div class="flex flex-col gap-3 text-sm text-foreground">
        <p>
          Chaque case dit à quel point <strong>deux lignes bougent ensemble</strong>, sur leurs
          <strong>rendements journaliers</strong> de la dernière année — pas sur leurs cours :
          deux titres qui montent chacun de leur côté paraîtraient liés par leur seule tendance.
        </p>
        <p class="rounded-md bg-muted px-3 py-2 font-mono text-xs">
          1,00 : mêmes variations, jour après jour
          <br />
          0,00 : aucun rapport
          <br />
          -1,00 : l'une monte quand l'autre baisse
        </p>
        <p>
          Une case <strong>chaude</strong> signale un <strong>doublon</strong> : deux fonds
          fortement corrélés ne diversifient rien, ils encaissent la même chute au même moment.
          Une case <strong>froide</strong> — corrélation faible ou négative — amortit.
        </p>
        <p>
          Le tableau ne garde que tes <strong>huit plus grosses lignes</strong> : au-delà, il ne
          se lit plus. Un tiret veut dire que la paire n'a pas assez de séances communes pour
          qu'un chiffre veuille dire quelque chose.
        </p>
        <p class="text-muted-foreground">
          Une corrélation décrit le passé récent, pas une promesse : elle grimpe souvent au pire
          moment, quand tout baisse ensemble.
        </p>
      </div>
    </DialogContent>
  </Dialog>
</template>
